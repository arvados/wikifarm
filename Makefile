textile-2.0.0:
	wget -c http://textile.thresholdstate.com/file_download/2/textile-2.0.0.tar.gz
	[ `md5sum textile-2.0.0.tar.gz | head -c 32` = c4f2454b16227236e01fc1c761366fe3 ]
	tar xzf textile-2.0.0.tar.gz
	patch -p0 <textile-2.0.0-php-5.2.4.patch
deb:
	dpkg-buildpackage -b -us -uc
clean:
	rm -rf textile-2.0.0
	rm -rf textile-2.0.0.tar.gz
	rm -rf DataTables-1.7.1
