RESOURCES=../work-in-progress-demo/game
SOURCES=$(wildcard $(RESOURCES)/scripts/en/oliver_*.rpyc)
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

$(PRESENTATION): $(AST) ast2pptx.php Common PHPPresentation
	@echo [ GEN] $@
	@$(PHP) ast2pptx.php $(AST) $@ $(RESOURCES) resources

$(AST): unrpyc rpy2ast.py $(SOURCES)
	@echo [ GEN] $@
	@$(PYTHON) rpy2ast.py $@ $(SOURCES)

unrpyc:
	git clone --depth=1 https://github.com/CensoredUsername/unrpyc $@
	mv $@/unrpyc.py $@/__init__.py

Common:
	git clone --depth=1 https://github.com/PHPOffice/Common $@

PHPPresentation:
	git clone --depth=1 https://github.com/PHPOffice/PHPPresentation $@
	cd $@ ; patch -p1 < ../phppresentation-deduplicate-resources.patch ; cd ..
