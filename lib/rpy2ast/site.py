import sys
import importlib

__module__ = sys.modules[__name__]


def load_site(site):
    """ Overwrite current with site. """
    mod = importlib.import_module(__name__ + '_' + site)
    __module__.__dict__.update(mod.__dict__)
