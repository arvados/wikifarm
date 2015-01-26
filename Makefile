dist: dist/textile-2.0.0
dist/textile-2.0.0: deps/textile-2.0.0.tar.gz
	mkdir -p dist
	tar -C dist -xzf deps/textile-2.0.0.tar.gz
	patch -p0 --directory=dist <patch/textile-2.0.0-php-5.2.4.patch
deb:
	dpkg-buildpackage -b -us -uc
clean:
	rm -rf dist/textile-2.0.0
	rm -rf dist/DataTables-1.7.1
