def parse_code(code):
    source = unicode(code.source)
    if source.startswith('"') and source.endswith('"'):
        return source.strip('"')
