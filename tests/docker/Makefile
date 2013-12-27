DOCKER := docker

all: latest

sag.tar:
	cd ../.. && tar -cvvf sag.tar .
	mv ../../sag.tar .

latest: sag.tar
	${DOCKER} build -t sbisbee/sag-php-tests .

clean:
	rm -f sag.tar
