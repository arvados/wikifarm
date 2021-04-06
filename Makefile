dist: dist/php-textile-2.5.5
dist/php-textile-2.5.5: deps/php-textile-2.5.5.tar.gz
	mkdir -p dist
	tar -C dist -xzf deps/php-textile-2.5.5.tar.gz
deb:
	dpkg-buildpackage -b -us -uc
clean:
	rm -rf dist/php-textile-2.5.5
	rm -rf dist/DataTables-1.7.1
