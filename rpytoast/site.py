import sys
from ast import walker

__module__ = sys.modules[__name__]


@walker('perform')
def perform(args):
    return rabbl_call('perform', args)

@walker('choose')
def choose(args):
    return rabbl_call('choose', args)

def rabbl_call(which, args):
    lang = '_en'
    if 'independent' in args:
        lang = ''
        args.remove('independent')
    if 'explicit' in args:
        args.remove('explicit')
    target = args[0].strip('"')
    type = {
        'perform': 'scene',
        'choose': 'choice'
    }[which]
    label = '{}_{}{}'.format(type, target, lang)
    return [{'type': 'call', 'label': label}]


def fixup(node):
    name = 'fixup_{}'.format(node['type'])
    if hasattr(__module__, name):
        return getattr(__module__, name)(node)

def fixup_say(node):
    CHARACTER_TAGS = {
        'mekkiu': 'mekki_snob',
        'allisonu': 'allison_crybaby',
        'darrenu': 'darren_prettyboy',
        'franklinu': 'professor',
        'wallaceu': 'wallace_jerk',
        'barista': 'barista',
        'tanya': 'tanya',
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
    if node['who'] in CHARACTER_TAGS:
        node['who'] = CHARACTER_TAGS[node['who']]

def fixup_show(node):
    fixup_image_defaults(node)
    fixup_image_paths(node)
    return should_filter_image(node)

def fixup_hide(node):
    fixup_image_defaults(node)
    fixup_image_paths(node)
    return should_filter_image(node)

def fixup_scene(node):
    fixup_image_defaults(node)
    fixup_image_paths(node)
    return should_filter_image(node)


def should_filter_image(node):
    return node['image'][0].startswith('phone') or node['image'][0].startswith('call') or node['image'][0].startswith('contact')

def fixup_image_defaults(node):
    SPRITE_OFFSETS = {
        'caprice': 160,
        'mekki': 100 ,
        'izaac': 100,
        'wallace': 175,
        'generic': 200,
        'hayley': 130,
        'lawe': 150,
        'tanya': 40,
        'darren': 175,
        'heather': 50,
        'allison': -25,
        'eileen': 50
    }
    if node['image'][0] == 'bg' and node['type'] != 'hide':
        at = node.get('at', {})
        at.setdefault('xpos', 0.5)
        at.setdefault('xanchor', 0.5)
        at.setdefault('ypos', 0.5)
        at.setdefault('yanchor', 0.5)
        node['at'] = at
    elif node['image'][0] in SPRITE_OFFSETS:
        yoff = SPRITE_OFFSETS[node['image'][0]]
        if 'at' in node and 'yoffset' in node['at']:
            node['at']['yoffset'] = str(int(node['at']['yoffset']) + yoff)
        else:
            node.setdefault('at', {})
            node['at']['yoffset'] = str(yoff)

def fixup_image_paths(node):
    PATHS = {
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

    if node['image'][0] in PATHS:
        node['image'][0] = PATHS[node['image'][0]]

    if node['image'][0] in SPRITES:
        node['image'][0] = 'sprites/' + node['image'][0]
