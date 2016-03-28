GAME_RESOURCES=../work-in-progress-demo/game
SITE=wipdemo
RESOURCE_DIRS=$(GAME_RESOURCES) resources
SOURCES=$(shell find $(GAME_RESOURCES) -name '*.rpyc')#$(wildcard $(RESOURCES)/scripts/en/oliver_*.rpyc)
PHP=php
PYTHON=python
PRESENTATION=presentation.pptx
AST=ast.json

all: $(PRESENTATION)
clean:
	@echo [ CLN]
	@rm -f $(PRESENTATION) $(AST)
distclean: clean
	@rm -rf unrpyc Common PHPPresentation

$(PRESENTATION): $(AST) ast2pptx.php $(wildcard ast2pptx/*.php) Common PHPPresentation
	@echo [ GEN] $@
	@$(PHP) ast2pptx.php $(AST) $@ $(SITE) $(RESOURCE_DIRS)

$(AST): unrpyc rpy2ast.py $(wildcard rpytoast/*.py) $(SOURCES)
	@echo [ GEN] $@
	@$(PYTHON) rpy2ast.py $@ $(SITE) $(SOURCES) $(RESOURCE_DIRS)

unrpyc:
	git clone --depth=1 https://github.com/CensoredUsername/unrpyc $@
	mv $@/unrpyc.py $@/__init__.py

Common:
	git clone --depth=1 https://github.com/PHPOffice/Common $@

PHPPresentation:
	git clone --depth=1 https://github.com/PHPOffice/PHPPresentation $@
	cd $@ ; patch -p1 < ../phppresentation-deduplicate-resources.patch ../phppresentation-flip-support.patch ; cd ..
