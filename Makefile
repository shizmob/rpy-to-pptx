include config.mk

RESOURCE_DIRS=$(GAME_RESOURCES) resources
SOURCES=$(shell find $(GAME_RESOURCES) -name '*.rpyc')#$(wildcard $(RESOURCES)/scripts/en/oliver_*.rpyc)
PRESENTATION=build/presentation.pptx
AST=build/ast.json

PHP=php
PYTHON=python

PHPPRESENTATION_REV=c35f774453ea9e4cd14c4bb6f6e086da5967c3ce
COMMON_REV=fc5243ef35c5f261b776d1a94033e54a9a482c70
UNRPYC_REV=889b88690967f5c598a489ffeb88a5518902f7f1


all: $(PRESENTATION)
clean:
	@echo [ CLN]
	@rm -rf build
distclean: clean
	@rm -rf lib/unrpyc lib/Common lib/PHPPresentation


$(PRESENTATION): $(AST) ast2pptx.php $(wildcard lib/ast2pptx/*.php) lib/Common lib/PHPPresentation | build
	@echo "[ GEN]" $@
	@$(PHP) ast2pptx.php $(AST) $@ $(SITE) $(RESOURCE_DIRS)

$(AST): build rpy2ast.py $(wildcard lib/rpy2ast/*.py) lib/unrpyc $(SOURCES)
	@echo "[ GEN]" $@
	@$(PYTHON) rpy2ast.py $@ $(SITE) $(SOURCES) $(RESOURCE_DIRS)


lib/unrpyc:
	@echo "[ GET]" $@
	@curl -fLO https://github.com/CensoredUsername/unrpyc/archive/$(UNRPYC_REV).tar.gz | tar -xzC lib
	@mv $@-* $@
	@mv $@/unrpyc.py $@/__init__.py

lib/Common:
	@echo "[ GET]" $@
	@curl -fLO https://github.com/PHPOffice/Common/archive/$(COMMON_REV).tar.gz | tar -xzC lib
	@mv $@-* $@

lib/PHPPresentation:
	@echo "[ GET]" $@
	curl -fLO https://github.com/PHPOffice/PHPPresentation/archive/$(PHPPRESENTATION_REV).tar.gz | tar -xzC lib
	@mv $@-* $@
	@cd $@ ; patch -p1 < ../../phppresentation-deduplicate-resources.patch ../../phppresentation-flip-support.patch ; cd ..

build:
	@mkdir build
