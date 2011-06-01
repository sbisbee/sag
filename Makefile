VERSION := $(shell sed --expression '/^Version /!d' --expression 's/^Version //' README)

SRC_DIR := src
TESTS_DIR := tests
EXAMPLES_DIR := examples
FILES := CHANGELOG LICENSE NOTICE README

PREFIX := .
DIST_DIR := ${PREFIX}/sag-${VERSION}

all: ${DIST_DIR}
	@@echo "Copying..."
	@@cp -r ${SRC_DIR} ${TESTS_DIR} ${EXAMPLES_DIR} ${FILES} ${DIST_DIR}

	@@echo "Archiving and compressing..."
	@@tar -zcvvf sag-${VERSION}.tar.gz ${DIST_DIR} > /dev/null

clean:
	@@rm -rf ${DIST_DIR} ${DIST_DIR}.tar.gz
  
${DIST_DIR}:
	@@mkdir -p ${DIST_DIR}
