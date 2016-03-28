rpy-to-pptx
===========

A Ren'Py to Powerpoint converter.

Usage
-----
1. Point the variables in the Makefile to your game directory.
2. Run `make` and hope.

Site overrides
--------------
Because the chance this will work out of the box is not very big,
site overrides can be made both in the AST generation part (`rpytoast`)
and in the visualization part (`ast2pptx`). Generally, the AST generation
should prepare everything so that the visualization needs minimal prodding,
which as much preprocessed as possible.

For AST generation, site overrides go in `rpytoast/site.py`.
For visualization, site overrides go in `ast2pptx/site.php`.
See the existing Twofold demo contents for hints.

Limitations
-----------
* Will always take the first branch (in an if-statement) or choice (in a menu);
* Limited animations, very limited transitions;
* No support for transforms with parameters;
* No support for screens;
* You have to draw the UI yourself (see ast2pptx/site.php)
* Not bug-compatible with Ren'Py's layouting;
* Likely missing support for stuff that the Twofold demo didn't use.
