import renpy

WALKERS = {}

def walker(n):
    def inner(f):
        WALKERS[n] = f
        return f
    return inner

def walk(s):
    if s.__class__ not in WALKERS:
        raise ValueError("No ATL handler defined for node type '{}'!".format(s.__class__))
    return WALKERS[s.__class__](s)


@walker(renpy.atl.RawTime)
@walker(renpy.atl.RawOn)
@walker(renpy.atl.RawRepeat)
def atl_skip(s):
    return {}

@walker(renpy.atl.RawMultipurpose)
def atl_multipurpose(s):
    r = {}
    for k, v in s.properties:
        r[k] = str(v)
    return r

@walker(renpy.atl.RawParallel)
def atl_parallel(s):
    r = {}
    for s in s.blocks:
        r.update(walk(s))
    return r

@walker(renpy.atl.RawBlock)
def atl_block(s):
    r = {}
    for s in s.statements:
        r.update(walk(s))
    return r


def parse_imspec(im):
    return {'image': list(im[0]), 'tag': im[2] or im[0][0], 'at': {}, 'transforms': im[3], 'behind': im[6]}

def parse_atl(atl, node):
    if not atl:
        return {}
    at = node.get('at', {})
    for s in atl.statements:
        at.update(walk(s))

    if 'xalign' in at:
        at.setdefault('xpos', at['xalign'])
        at.setdefault('xanchor', at['xalign'])
        del at['xalign']
    if 'yalign' in at:
        at.setdefault('ypos', at['yalign'])
        at.setdefault('yanchor', at['yalign'])
        del at['yalign']
    return {'at': at}
