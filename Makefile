# Determine version number from README (will include "-UNRELEASED")
VERSION := $(shell sed --expression '/^Version /!d' --expression 's/^Version //' README)

# Main directories and files
PREFIX := .
SRC_DIR := ${PREFIX}/src
TESTS_DIR := ${PREFIX}/tests
EXAMPLES_DIR := ${PREFIX}/examples
FILES := ${PREFIX}/CHANGELOG ${PREFIX}/LICENSE ${PREFIX}/NOTICE ${PREFIX}/README

# Distribution locations
DIST_DIR := ${PREFIX}/sag-${VERSION}
DIST_FILE := ${DIST_DIR}.tar.gz
DIST_FILE_SIG := ${DIST_FILE}.sig

# PHPUnit related tools and files
TESTS_BOOTSTRAP := ${TESTS_DIR}/bootstrap.bsh
TESTS_CONFIG := ${TESTS_DIR}/phpunitConfig.xml
TESTS_PHP_INCLUDE_PATH := $(shell php -r 'echo ini_get("include_path");'):$(SRC_DIR)
TESTS_PHPUNIT_OPTS := -d "include_path=${TESTS_PHP_INCLUDE_PATH}" \
			--configuration=${TESTS_CONFIG}

dist: clean ${DIST_DIR} test
	@@echo "[ Copying... ]"
	@@cp -r ${SRC_DIR} ${TESTS_DIR} ${EXAMPLES_DIR} ${FILES} ${DIST_DIR}

	@@echo "[ Archiving and compressing... ]"
	@@tar -zcvvf ${DIST_FILE} ${DIST_DIR} > /dev/null
	@@rm -rf ${DIST_DIR}
test:
	@@echo "[ Bootstrapping tests... ]" ; \
		${TESTS_BOOTSTRAP} && \
		echo "[ Running tests... ]" ; \
		phpunit ${TESTS_PHPUNIT_OPTS} ${TESTS_DIR}

sign: dist
	@@gpg --output ${DIST_FILE_SIG} --detach-sig ${DIST_FILE}

clean:
	@@echo "[ Removing files... ]"
	@@rm -rf ${DIST_DIR} ${DIST_FILE} ${DIST_FILE_SIG}
  
${DIST_DIR}:
	@@mkdir -p ${DIST_DIR}
