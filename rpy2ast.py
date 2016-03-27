import sys
import os.path
import json
import unrpyc
from rpytoast import index, fixup, resolve, simplify, ast, atl, site


if len(sys.argv) < 3:
    print >>sys.stderr, 'usage: {} <out.json> <file.rpyc> [<file.rpyc> ...] <resdir> [<resdir> ...]'.format(sys.argv[0])
    sys.exit(1)


scripts = []
resource_dirs = []
nodes = []

for path in sys.argv[2:]:
    if os.path.isdir(path):
        resource_dirs.append(os.path.realpath(path))
    else:
        scripts.append(path)


for script in scripts:
    print 'Parsing', script, '...'

    with open(script, 'rb') as f:
        tree = unrpyc.read_ast_from_file(f)

    for s in map(ast.walk, tree):
      nodes.extend(s)

print 'Indexing into AST...'
tree = index(nodes)
print 'Fixing up mistakes...'
fixup(tree)
print 'Resolving references...'
resolve(tree, resource_dirs)
print 'Converting to JSON-friendly format...'
simplify(tree)
print 'Writing JSON...'
with open(sys.argv[1], 'wb') as f:
    json.dump(tree, f)
