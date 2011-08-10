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

# Distribution locations
DIST_DIR := ${PREFIX}sag-${VERSION}
DIST_FILE := ${DIST_DIR}.tar.gz
DIST_FILE_SIG := ${DIST_FILE}.sig

# PHPUnit related tools and files
TESTS_BOOTSTRAP := ${TESTS_DIR}/bootstrap.bsh
TESTS_CONFIG := ${TESTS_DIR}/phpunitConfig.xml
TESTS_PHP_INCLUDE_PATH := $(shell php -r 'echo ini_get("include_path");'):$(SRC_DIR)
TESTS_COVERAGE_DIR := ${TESTS_DIR}/coverage
TESTS_PHPUNIT_OPTS := -d "include_path=${TESTS_PHP_INCLUDE_PATH}" \
			--configuration=${TESTS_CONFIG}

# PHPDocs related tools and files
DOCS_DIR := ${PREFIX}docs
PHPDOC_OPTS := -d ${SRC_DIR} -t ${DOCS_DIR} -o HTML:Smarty:PHP -dn Core -ti "Sag Documentation"

# Build the distribution
dist: clean ${DIST_DIR} check
	cp -r ${SRC_DIR} ${TESTS_DIR} ${EXAMPLES_DIR} ${FILES} ${DIST_DIR}

	tar -zcvvf ${DIST_FILE} ${DIST_DIR}
	rm -rf ${DIST_DIR}

# Run the tests
check:
	for file in ${SRC_DIR}/*.php ${TESTS_DIR}/*.php; do \
	  php -l "$$file"; \
	done

	@echo ""

	${TESTS_BOOTSTRAP}
	${PHPUNIT} ${TESTS_PHPUNIT_OPTS} ${TESTS_DIR}

# Run the tests with code coverage
checkCoverage:
	$(MAKE) check TESTS_PHPUNIT_OPTS="${TESTS_PHPUNIT_OPTS} --coverage-html=${TESTS_COVERAGE_DIR}"

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
		${TESTS_COVERAGE_DIR} ${DOCS_DIR}
  
# Create the distribution directory that will be archived
${DIST_DIR}:
	mkdir -p ${DIST_DIR}
