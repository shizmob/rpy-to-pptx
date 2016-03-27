import sys
import glob
import ast, atl, site


def index(nodes):
    labels = {}
    images = {}
    transforms = {}

    site.defaults(labels, images, transforms)

    label = None
    for node in nodes:
        if node['type'] == 'label':
            if label:
                labels[label].append({'type': 'jump', 'label': node['label']})
            label = node['label']
            labels[label] = []
        elif node['type'] == 'return':
            label = None
        elif node['type'] == 'image':
            images[node['name']] = node
        elif node['type'] == 'transform':
            transforms[node['name']] = node
        elif label:
            labels[label].append(node)

    return {'entry_point': 'start', 'script': labels, 'transforms': transforms, 'images': images}

def fixup(ast):
    for script in ast['script'].values():
        offset = 0
        for i, node in enumerate(script[:]):
            if node['type'] == 'say':
                # Delete empty statements.
                if not node['what']:
                    del script[i + offset]
                    offset -= 1
                    continue
                # Merge old-style {nw} interrupts.
                for j in range(i + offset - 1, 0, -1):
                    if script[j]['type'] == 'say':
                        if node['what'].startswith(script[j]['what']):
                            script[j]['what'] = node['what']
                            del script[i + offset]
                            offset -= 1
                        break
                # Merge extensions.
                if node['who'] == 'extend':
                    for j in range(i + offset - 1, 0, -1):
                        if script[j]['type'] == 'say':
                            script[j]['what'] += node['what']
                            del script[i + offset]
                            offset -= 1
                            break
            elif node['type'] == 'transition':
                # Move transitions to before were they are supposed to apply, not after.
                for j in range(i + offset - 1, 0, -1):
                    if script[j]['type'] not in ('say', 'doublesay'):
                        script.insert(j, node)
                        del script[i + offset + 1]
                        break
            # Game-specific fixups.
            if site.fixup(node):
                del script[i + offset]
                offset -= 1

def resolve(ast, resource_dirs):
    for script in ast['script'].values():
        offset = 0
        for i, node in enumerate(script[:]):
            if node['type'] == 'call' and node['label'] not in ast['script']:
                print >>sys.stderr, 'Unknown call label: {}'.format(node['label'])
                del script[i + offset]
                offset -= 1
            if 'transforms' in node:
                at = {}
                for transform in node['transforms']:
                    if '(' in transform:
                        transform, params = transform.split('(')
                        params = params.rstrip(')').split()
                    else:
                        params = []
                    if transform not in ast['transforms']:
                        print >>sys.stderr, 'Unknown ATL transform: {}'.format(transform)
                        continue
                    at.update(ast['transforms'][transform]['at'])
                at.update(node['at'])
                node['at'] = at
            if 'image' in node and node['type'] != 'hide':
                res = find_resource(node['image'], resource_dirs, ast['images'])
                if res:
                    node['image'] = res
                else:
                    if len(node['image']) > 1:
                        print >>sys.stderr, 'Can\'t find resource: {}'.format(node['image'])
                    node['image'] = None


RESOURCE_CACHE = {}

def find_resource(name, dirs, images):
    key = tuple(name)
    if key in images and images[key]['value']:
        name = images[key]['value']
    else:
        name = '/'.join(name) + '.*'

    if name not in RESOURCE_CACHE:
        for path in dirs:
            p = name
            while True:
                pattern = path + '/' + p
                files = glob.glob(pattern)
                if files:
                    RESOURCE_CACHE[name] = files[0]
                    break
                pos = p.rfind('/')
                if pos < 0:
                    break
                p = p[:pos] + '_' + p[pos + 1:]
            if name in RESOURCE_CACHE:
                break
        else:
            return None

    return RESOURCE_CACHE[name]


def simplify(ast):
    for name, val in ast['images'].copy().items():
        ast['images']['/'.join(name)] = val
        del ast['images'][name]
