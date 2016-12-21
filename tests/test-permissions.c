/* This tries to read from some file that is group root, but not world
 * readable. It should fail with WRONG-ANSWER, because we first check.
 * If anything is not as expected it should generate a RUN-ERROR.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <error.h>
#include <errno.h>

#define ROOTFILE "/etc/root-permission-test.txt"

int main(int argc, char **argv)
{
	FILE *f;
	char line[256];

	printf("My effective/real group IDs are: %d/%d\n", getgid(), getegid());

	f = fopen(ROOTFILE,"r");

	if ( f!=NULL ) {
		if ( fgets(line, 255, f)==NULL ) {
			printf("Warning: opened file, but reading with fgets() failed.\n");
		}
		error(1, 0, "error: we can read from '%s':\n%s", ROOTFILE, line);
	}

	if ( errno!=EACCES ) {
		error(1, errno, "unexpected error occurred opening '%s'", ROOTFILE);
	}

	printf("Permission denied reading from '%s' as expected.\n", ROOTFILE);

	return 0;
}
