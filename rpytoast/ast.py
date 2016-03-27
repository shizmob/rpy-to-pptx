import re
import renpy
from atl import parse_imspec, parse_atl


WALKERS = {}

def walker(n):
    def inner(f):
        WALKERS[n] = f
        return f
    return inner

def walk(node):
    return WALKERS[node.__class__](node)


@walker(renpy.ast.Pass)
@walker(renpy.ast.Screen)
@walker(renpy.ast.EarlyPython)
@walker('play')
@walker('stop')
def skip(node):
    return []

@walker(list)
def block(node):
    r = []
    for x in node:
        r.extend(walk(x))
    return r

@walker(renpy.ast.Label)
def label(node):
    nodes = [{'type': 'label', 'label': node.name}]
    nodes.extend(walk(node.block))
    return nodes

@walker(renpy.ast.Return)
def ret(node):
    return [{'type': 'return'}]

@walker(renpy.ast.Jump)
def goto(node):
    return [{'type': 'jump', 'label': node.target}]

@walker(renpy.ast.Init)
def init(node):
    return walk(node.block)

@walker(renpy.ast.Define)
def define(node):
    return [{'type': 'image', 'name': tuple([node.varname]), 'value': unicode(node.code.source)}]

@walker(renpy.ast.Image)
def image(node):
    b = {'type': 'image', 'name': tuple(node.imgname), 'value': unicode(node.code.source)}
    b.update(parse_atl(node.atl, b))
    return [b]

@walker(renpy.ast.Transform)
def transform(node):
    b = {'type': 'transform', 'name': node.varname}
    b.update(parse_atl(node.atl, b))
    return [b]

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
    b.update(parse_atl(node.atl, b))
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
    return [{'type': '{}_ui'.format(args[0])}]

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
    if args[0] == 'screen':
        return call_screen(args[1:])

    if args[0].startswith('switch_scene'):
        new = args[2].strip("'").split()
        return [{'type': 'scene', 'image': new, 'tag': new[0]}]

    return [{'type': 'call', 'label': ' '.join(args)}]

def call_screen(args):
    return []

@walker(renpy.ast.Python)
def python(node):
    r = []
    code = unicode(node.code.source)
    for match in re.findall('renpy.transition\((.+?),.+?\)', code):
        if match == 'None':
            continue
        r.append({'type': 'transition', 'transition': match})
    return r

@walker(renpy.ast.UserStatement)
def user_statement(node):
    p = node.line.split()
    return WALKERS[p[0]](p[1:])


def parse_text(text):
    return re.sub(r'{.+?}', '', text)
