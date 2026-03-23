.PHONY: link-rr

# sh: command -v rr > /dev/null 2>&1 && ln -sf $(which rr) rr || (echo "rr not found" && exit 1)
link-rr:
	@command -v rr > /dev/null 2>&1 && ln -sf $$(which rr) rr || (echo "Error: 'rr' not found in PATH" && exit 1)
