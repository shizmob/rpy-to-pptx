import sys
import re
import json
import unrpyc
import renpy


WALKERS = {}
ATL_WALKERS = {}

def walker(n):
    def inner(f):
        WALKERS[n] = f
        return f
    return inner

def atl_walker(n):
    def inner(f):
        ATL_WALKERS[n] = f
        return f
    return inner

def walk(node):
    return WALKERS[node.__class__](node)

def walk_atl(s):
    return ATL_WALKERS[s.__class__](s)


@walker(renpy.ast.Return)
@walker(renpy.ast.Pass)
@walker(renpy.ast.Transform)
@walker(renpy.ast.Screen)
@walker('play')
@walker('stop')
@walker('perform')
def skip(node):
    return []

@walker(list)
def l(node):
    r = []
    for x in node:
        r.extend(walk(x))
    return r

@walker(renpy.ast.Label)
@walker(renpy.ast.Init)
def block(node):
    return walk(node.block)

@walker(renpy.ast.If)
def condition(node):
    return walk(node.entries[0][1])

@walker(renpy.ast.Say)
def say(node):
    return [{'type': 'say', 'who': node.who, 'what': parse_text(node.what)}]

@walker(renpy.ast.Scene)
def scene(node):
    b = {'type': 'scene'}
    b.update(parse_imspec(node.imspec))
    return [b]

@walker(renpy.ast.Show)
def show(node):
    b = {'type': 'show'}
    b.update(parse_imspec(node.imspec))
    b.update(parse_atl(node.atl))
    return [b]

@walker(renpy.ast.Hide)
def hide(node):
    b = {'type': 'hide'}
    b.update(parse_imspec(node.imspec))
    return [b]

@walker(renpy.ast.With)
def transition(node):
    return [{'type': 'transition', 'transition': node.expr}]

@walker('window')
def window(args):
    return [{'type': args[0], 'image': ['textbox']}]

@walker('doublespeak')
def doublespeak(args):
    return [{'type': 'doublesay', 'who': [args[0], args[1]], 'what': ' '.join(args[2:])}]

@walker(renpy.ast.Call)
def ast_call(node):
    if node.arguments:
        args = [x[1] for x in node.arguments.arguments]
    else:
        args = []
    return call([node.label] + args)

@walker('call')
def call(args):
    if args[0].startswith('switch_scene'):
        new = args[2].strip("'").split()
        return [{'type': 'scene', 'image': new}]
    return []

@walker(renpy.ast.Python)
def python(node):
    r = []
    code = str(node.code.source)
    for match in re.findall('renpy.transition\((.+?),.+?\)', code):
        if match == 'None':
            continue
        r.append({'type': 'transition', 'transition': match})
    return r

@walker(renpy.ast.UserStatement)
def user_statement(node):
    p = node.line.split()
    return WALKERS[p[0]](p[1:])

@atl_walker(renpy.atl.RawTime)
def atl_skip(s):
    return {}

@atl_walker(renpy.atl.RawMultipurpose)
def atl_multipurpose(s):
    r = {}
    for k, v in s.properties:
        r[k] = str(v)
    return r

@atl_walker(renpy.atl.RawParallel)
def atl_parallel(s):
    r = {}
    for s in s.blocks:
        r.update(walk_atl(s))
    return r

@atl_walker(renpy.atl.RawBlock)
def atl_block(s):
    r = {}
    for s in s.statements:
        r.update(walk_atl(s))
    return r

def parse_imspec(im):
    return {'image': list(im[0])}

def parse_atl(atl):
    if not atl:
        return {}
    properties = {}
    for s in atl.statements:
        properties.update(walk_atl(s))
    return {'at': properties}

def parse_text(text):
    return re.sub(r'{.+?}', '', text)


def fixup(ast):
    offset = 0
    for i, node in enumerate(ast[:]):
        if node['type'] == 'say':
            CHARACTER_TAGS = {
                'mekkiu': 'mekki_snob',
                'allisonu': 'allison_crybaby',
                'darrenu': 'darren_prettyboy',
                'franklinu': 'professor',
                'wallaceu': 'wallace_jerk',
                'Barista': 'barista',
                'Tanya': 'tanya',
                'tanyauu': 'tanya_voice',
                'tanyau': 'tanya_shorty',
                'heatheru': 'heather_ginger',
                'hayleyu': 'hayley_phones',
                'izaacu': 'izaac_burglar',
                'eileenu': 'eileen_rudegirl',
                'darrenu': 'guy',
                'franklin': 'professor',
                'waitress': 'barista',
                'unknown': 'mystery'
            }
            if not node['what']:
                del ast[i + offset]
                offset -= 1
                continue
            for j in range(i + offset - 1, 0, -1):
                if ast[j]['type'] == 'say':
                    if node['what'].startswith(ast[j]['what']):
                        ast[j]['what'] = node['what']
                        del ast[i + offset]
                        offset -= 1
                    break
            if node['who'] == 'extend':
                for j in range(i + offset - 1, 0, -1):
                    if ast[j]['type'] == 'say':
                        ast[j]['what'] += node['what']
                        del ast[i + offset]
                        offset -= 1
                        break
            elif node['who'] in CHARACTER_TAGS:
                node['who'] = CHARACTER_TAGS[node['who']]
        elif node['type'] == 'transition':
            for j in range(i + offset - 1, 0, -1):
                if ast[j]['type'] not in ('say', 'doublesay'):
                    ast.insert(j, node)
                    del ast[i + offset + 1]
                    break
        elif node['type'] in ('show', 'hide', 'scene'):
            FIXUPS = {
                'bg': 'bgs',
                'cg': 'cgs',
                'misc': 'vfx',
                'title': 'vfx/title',
                'note': 'vfx/notes',
                'drawing': 'vfx/drawings'
            }
            SPRITES = [
                'allison', 'barista', 'caprice', 'crowd', 'darren', 'eileen',
                'generic', 'hayley', 'heather', 'izaac', 'lawe', 'mekki',
                'placeholder', 'tanya', 'wallace'
            ]
            if node['image'][0] == 'bg' and node['type'] != 'hide':
                at = node.get('at', {})
                at.setdefault('xalign', 0.5)
                at.setdefault('yalign', 0.5)
                node['at'] = at
            if node['image'][0] in FIXUPS:
                node['image'][0] = FIXUPS[node['image'][0]]
            if node['image'][0] in SPRITES:
                node['image'].insert(0, 'sprites')
            if node['image'][0].startswith('phone') or node['image'][0].startswith('call') or node['image'][0].startswith('contact'):
                del ast[i + offset]
                offset -= 1
            if 'at' in node:
                if 'xalign' in node['at']:
                    node['at'].setdefault('xpos', node['at']['xalign'])
                    node['at'].setdefault('xanchor', node['at']['xalign'])
                    del node['at']['xalign']
                if 'yalign' in node['at']:
                    node['at'].setdefault('ypos', node['at']['yalign'])
                    node['at'].setdefault('yanchor', node['at']['yalign'])
                    del node['at']['yalign']


if len(sys.argv) < 3:
    print >>sys.stderr, 'usage: {} <out.json> <file.rpyc> [<file.rpyc> ...]'.format(sys.argv[0])
    sys.exit(1)

r = []
for path in sys.argv[2:]:
    with open(path, 'rb') as f:
        ast = unrpyc.read_ast_from_file(f)

    print 'Parsing', path, '...'
    for s in map(walk, ast):
      r.extend(s)

print 'Fixing up AST...'
fixup(r)

with open(sys.argv[1], 'wb') as f:
    json.dump(r, f)
