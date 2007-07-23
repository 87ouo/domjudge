/* Return the name-within-directory of a file name.
   Copyright (C) 1996,97,98,2002 Free Software Foundation, Inc.
   This file is part of the GNU C Library.

   The GNU C Library is free software; you can redistribute it and/or
   modify it under the terms of the GNU Lesser General Public
   License as published by the Free Software Foundation; either
   version 2.1 of the License, or (at your option) any later version.

   The GNU C Library is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
   Lesser General Public License for more details.

   You should have received a copy of the GNU Lesser General Public
   License along with the GNU C Library; if not, write to the Free
   Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
   Boston, MA  02110-1301, USA. */

#include <string.h>

#if defined(__CYGWIN__) || defined(__CYGWIN32__)
#define PATHSEP "\\/"
#else
#define PATHSEP "/"
#endif

char *gnu_basename(const char *filename)
{
	char *p;

	for(p=(char *)filename+strlen(filename)-1; p>=filename; p--) {
		if ( strchr(PATHSEP,*p)!=NULL ) break;
	}

	return p+1;
}
