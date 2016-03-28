import sys
import os.path
import json
import unrpyc
import rpytoast


if len(sys.argv) < 3:
    print >>sys.stderr, 'usage: {} <out.json> <site> <file.rpyc> [<file.rpyc> ...] <resdir> [<resdir> ...]'.format(sys.argv[0])
    sys.exit(1)

outfile = sys.argv[1]
site = sys.argv[2]
paths = sys.argv[3:]

if site:
    rpytoast.load_site(site)

scripts = []
resource_dirs = []
nodes = []

for path in paths:
    if os.path.isdir(path):
        resource_dirs.append(os.path.realpath(path))
    else:
        scripts.append(path)


for script in scripts:
    print 'Parsing', script, '...'

    with open(script, 'rb') as f:
        tree = unrpyc.read_ast_from_file(f)

    for node in tree:
      s = rpytoast.ast.walk(node)
      nodes.extend(s)

print 'Indexing into AST...'
tree = rpytoast.index(nodes)
print 'Fixing up mistakes...'
rpytoast.fixup(tree)
print 'Resolving references...'
rpytoast.resolve(tree, resource_dirs)
print 'Converting to JSON-friendly format...'
rpytoast.simplify(tree)
print 'Writing JSON...'
with open(outfile, 'wb') as f:
    json.dump(tree, f)
