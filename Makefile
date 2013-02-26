# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

# Determine version number from README (will include "-UNRELEASED")
VERSION := $(shell sed --expression '/^Version /!d' --expression 's/^Version //' README)

# Main directories and files
PREFIX :=
SRC_DIR := ${PREFIX}src
TESTS_DIR := ${PREFIX}tests
EXAMPLES_DIR := ${PREFIX}examples
FILES := ${PREFIX}CHANGELOG ${PREFIX}LICENSE ${PREFIX}NOTICE ${PREFIX}README

# Main binaries
PHPDOC := phpdoc
PHPUNIT := phpunit
GPG := gpg
MD5SUM := md5sum
SHA1SUM := sha1sum

# Distribution locations
DIST_DIR := ${PREFIX}sag-${VERSION}
DIST_FILE := ${DIST_DIR}.tar.gz
DIST_FILE_SIG := ${DIST_FILE}.sig
DIST_FILE_SHA1 := ${DIST_FILE}.sha
DIST_FILE_MD5 := ${DIST_FILE}.md5

# PHPUnit related tools and files
TESTS_BOOTSTRAP := ${TESTS_DIR}/bootstrap.bsh
TESTS_PHP_INCLUDE_PATH := $(shell php -r 'echo ini_get("include_path");'):$(SRC_DIR)
TESTS_COVERAGE_DIR := ${TESTS_DIR}/coverage

TESTS_CONFIG_NATIVE_SOCKETS := ${TESTS_DIR}/phpunitConfig-nativeSockets.xml
TESTS_CONFIG_CURL := ${TESTS_DIR}/phpunitConfig-cURL.xml
TESTS_CONFIG_SSL_CURL := ${TESTS_DIR}/phpunitConfig-SSL-cURL.xml

TESTS_PHPUNIT_OPTS_BASE := -d "include_path=${TESTS_PHP_INCLUDE_PATH}" \
                            -d "error_reporting=\"E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR\""

TESTS_PHPUNIT_OPTS_NATIVE := ${TESTS_PHPUNIT_OPTS_BASE} --configuration=${TESTS_CONFIG_NATIVE_SOCKETS}
TESTS_PHPUNIT_OPTS_CURL := ${TESTS_PHPUNIT_OPTS_BASE} --configuration=${TESTS_CONFIG_CURL}
TESTS_PHPUNIT_OPTS_SSL_CURL := ${TESTS_PHPUNIT_OPTS_BASE} --configuration=${TESTS_CONFIG_SSL_CURL}

# PHPDocs related tools and files
DOCS_DIR := ${PREFIX}docs
PHPDOC_OPTS := -d ${SRC_DIR} -t ${DOCS_DIR} --title "Sag Documentation" --defaultpackagename "Core" --template "abstract"

# Build the distribution
dist: clean ${DIST_DIR}
	cp -r ${SRC_DIR} ${TESTS_DIR} ${EXAMPLES_DIR} ${FILES} ${DIST_DIR}

	tar -zcvvf ${DIST_FILE} ${DIST_DIR}
	rm -rf ${DIST_DIR}

	${SHA1SUM} ${DIST_FILE} > ${DIST_FILE_SHA1}
	${MD5SUM} ${DIST_FILE} > ${DIST_FILE_MD5}

lint:
	for file in ${SRC_DIR}/*.php ${TESTS_DIR}/*.php; do \
	  php -l "$$file"; \
	done

# Run tests with native sockets
checkNative:
	@echo "Testing with native sockets..."

	${TESTS_BOOTSTRAP}
	@${PHPUNIT} ${TESTS_PHPUNIT_OPTS_NATIVE} ${TESTS_DIR}

# Run tests with cURL
checkCURL:
	@echo "Testing with cURL..."

	${TESTS_BOOTSTRAP}
	@${PHPUNIT} ${TESTS_PHPUNIT_OPTS_CURL} ${TESTS_DIR}

# Runs tests with cURL and SSL
checkCURL_SSL:
	@echo "Testing with cURL + SSL..."

	${TESTS_BOOTSTRAP}
	@${PHPUNIT} ${TESTS_PHPUNIT_OPTS_SSL_CURL} ${TESTS_DIR}

# Run the tests
check: lint checkNative checkCURL

# Run the native socket tests with code coverage
checkCoverage:
	$(MAKE) checkNative TESTS_PHPUNIT_OPTS_NATIVE="${TESTS_PHPUNIT_OPTS_NATIVE} --coverage-html=${TESTS_COVERAGE_DIR}"

checkCoverageCURL:
	${MAKE} checkCURL TESTS_PHPUNIT_OPTS_CURL="${TESTS_PHPUNIT_OPTS_CURL} --coverage-html=${TESTS_COVERAGE_DIR}"

# Generate documentation with PHPDocumentation
docs:
	rm -rf ${DOCS_DIR}
	${PHPDOC} ${PHPDOC_OPTS}

# Sign the distribution
sign: dist
	${GPG} --output ${DIST_FILE_SIG} --detach-sig ${DIST_FILE}

# Remove all distribution and other build files
clean:
	rm -rf ${DIST_DIR} ${DIST_FILE} ${DIST_FILE_SIG} \
		${TESTS_COVERAGE_DIR} ${DOCS_DIR} ${DIST_FILE_MD5} \
		${DIST_FILE_SHA1}
  
# Create the distribution directory that will be archived
${DIST_DIR}:
	mkdir -p ${DIST_DIR}
