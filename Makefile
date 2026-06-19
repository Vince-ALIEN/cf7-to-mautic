PLUGIN_SLUG = cf7-to-mautic

.PHONY: zip test

zip:
	cd .. && zip -r $(PLUGIN_SLUG).zip $(PLUGIN_SLUG)/ \
		--exclude "$(PLUGIN_SLUG)/vendor/*" \
		--exclude "$(PLUGIN_SLUG)/tests/*" \
		--exclude "$(PLUGIN_SLUG)/.git/*" \
		--exclude "$(PLUGIN_SLUG)/.claude/*" \
		--exclude "$(PLUGIN_SLUG)/composer.json" \
		--exclude "$(PLUGIN_SLUG)/composer.lock" \
		--exclude "$(PLUGIN_SLUG)/phpunit.xml" \
		--exclude "$(PLUGIN_SLUG)/patchwork.json" \
		--exclude "$(PLUGIN_SLUG)/.phpunit.result.cache" \
		--exclude "$(PLUGIN_SLUG)/.gitignore" \
		--exclude "$(PLUGIN_SLUG)/Makefile"
	@echo "Archive creee : $(shell dirname $(CURDIR))/$(PLUGIN_SLUG).zip"

test:
	./vendor/bin/phpunit
