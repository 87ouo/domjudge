#!/usr/bin/env bats

# These tests assume presence of a running DOMjudge instance at the
# compiled in baseurl that has the DOMjudge example data loaded.

setup() {
  export SUBMITCONTEST="demo"
} 

@test "contest via parameter overrides environment" {
  run ./submit -c bestaatniet
  echo $output | grep "error: no (valid) contest specified"
  run ./submit --contest=bestaatookniet
  echo $output | grep "error: no (valid) contest specified"
  [ "$status" -eq 1 ]
}

@test "hello problem id and name are in help output" {
  run ./submit --help
  echo $output | grep "hello *- *Hello World"
  [ "$status" -eq 0 ]
}

@test "languages and extensions are in help output" {
  run ./submit --help
  echo $output | grep "C: *c"
  echo $output | grep "C++: *cpp, cc, cxx, c++"
  echo $output | grep "Java: *java"
}

@test "stale file emits warning" {
  cp -a ../tests/test-hello.c $BATS_TMPDIR
  run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
  echo $output | grep "test-hello.c' has not been modified for [0-9]* minutes!"
}

@test "recent file omits warning" {
  touch $BATS_TMPDIR/test-hello.c
  run ./submit -p hello $BATS_TMPDIR/test-hello.c <<< "n"
  echo $output | grep -v "test-hello.c' has not been modified for [0-9]* minutes!"
}

@test "binary file emits warning" {
  cp ./submit $BATS_TMPDIR/binary.c
  run ./submit -p hello $BATS_TMPDIR/binary.c <<< "n"
  echo $output | grep "binary.c' is detected as binary/data!"
}

@test "empty file emits warning" {
  touch $BATS_TMPDIR/empty.c
  run ./submit -p hello $BATS_TMPDIR/empty.c <<< "n"
  echo $output | grep "empty.c' is empty"
}

@test "detect problem name and language" {
  cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
  run ./submit $BATS_TMPDIR/hello.java <<< "n"
  [ "${lines[1]}" = "Submission information:" ]
  [ "${lines[4]}" = "  problem:     hello" ]
  [ "${lines[5]}" = "  language:    Java" ]
}

@test "options override detection of problem name and language" {
  cp ../tests/test-hello.java $BATS_TMPDIR/hello.java
  run ./submit -p boolfind -l cpp $BATS_TMPDIR/hello.java <<< "n"
  [ "${lines[1]}" = "Submission information:" ]
  [ "${lines[4]}" = "  problem:     boolfind" ]
  [ "${lines[5]}" = "  language:    C++" ]
}

@test "detect entry point Java" {
  run ./submit -p hello ../tests/test-hello.java <<< "n"
  [ "${lines[7]}" = "  entry point: test-hello" ]
}

@test "detect entry point Python" {
  skip "Python not enabled in the default installation"
  run ./submit -p hello ../tests/test-hello.py <<< "n"
  [ "${lines[7]}" = "  entry point: test_hello.py" ]
}

@test "detect entry point Kotlin" {
  skip "Kotlin not enabled in the default installation"
  run ./submit -p hello ../tests/test-hello.kt <<< "n"
  [ "${lines[7]}" = "  entry point: Test_helloKt" ]
}

@test "accept multiple files" {
  cp ../tests/test-hello.java ../tests/test-classname.java ../tests/test-package.java $BATS_TMPDIR/
  run ./submit -p hello $BATS_TMPDIR/test-*.java <<< "n"
  [ "${lines[2]}" = "  filenames:   $BATS_TMPDIR/test-classname.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java" ]
}

@test "deduplicate multiple files" {
  cp ../tests/test-hello.java ../tests/test-package.java $BATS_TMPDIR/
  run ./submit -p hello $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java <<< "n"
  [ "${lines[2]}" = "  filenames:   $BATS_TMPDIR/test-hello.java $BATS_TMPDIR/test-package.java" ]
}

@test "submit solution" {
  run ./submit -y -p hello ../tests/test-hello.c
  echo $output | grep "Submission received, id = s[0-9]*"
}
