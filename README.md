rpy-to-pptx
===========

A Ren'Py to Powerpoint converter.

Usage
-----
1. Point the variables in the Makefile to your game directory.
2. Run `make` and hope.

Limitations
-----------
* Will always take the first branch (in an if-statement) or choice (in a menu);
* Limited animations, very limited transitions;
* No support for transforms with parameters;
* No support for screens;
* Not bug-compatible with Ren'Py's layouting;
* Likely missing support for stuff that the Twofold demo didn't use.
