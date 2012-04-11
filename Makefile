textile-2.0.0: textile-2.0.0.tar.gz
	tar xzf textile-2.0.0.tar.gz
	patch -p0 <textile-2.0.0-php-5.2.4.patch
deb:
	dpkg-buildpackage -b -us -uc
clean:
	rm -rf textile-2.0.0
	rm -rf DataTables-1.7.1
