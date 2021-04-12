dist: dist/php-textile-2.5.5 dist/Auth_remoteuser
dist/php-textile-2.5.5: deps/php-textile-2.5.5.tar.gz
	mkdir -p dist
	tar -C dist -xzf deps/php-textile-2.5.5.tar.gz
dist/Auth_remoteuser: deps/Auth_remoteuser-REL1_35-6f570b8.tar.gz
	@mkdir -p dist
	@tar -C dist -xzf deps/Auth_remoteuser-REL1_35-6f570b8.tar.gz
deb:
	dpkg-buildpackage -b -us -uc
clean:
	rm -rf dist/php-textile-2.5.5
	rm -rf dist/Auth_remoteuser
	rm -rf dist/DataTables-1.7.1
