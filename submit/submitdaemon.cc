/*
   submitdaemon -- server for the submit program.
   Copyright (C) 2004 Peter van de Werken, Jaap Eldering.

   Based on submitdaemon.pl by Eelco Dolstra.
   
   $Id$

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 */

#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <syslog.h>
#include <string.h>
#include <sys/wait.h>
#include <sys/socket.h>
#include <netdb.h>
#include <poll.h>
#include <signal.h>
#include <getopt.h>
#include <pwd.h>
#include <libgen.h>

/* C++ includes for easy string handling */
using namespace std;
#include <iostream>
#include <sstream>
#include <string>

/* System/site specific config */
#include "../etc/config.h"

/* Logging and error functions */
#include "../lib/lib.error.h"

/* Include some functions which are not always available */
#include "../lib/mkstemps.h"
#include "../lib/basename.h"

/* Common send/receive functions */
#include "submitcommon.h"

/* These defines are needed in 'version' */
#define DOMJUDGE_PROGRAM "DOMjudge/" DOMJUDGE_VERSION
#define PROGRAM "submitdaemon"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

/* Variables defining logmessages verbosity to stderr/logfile */
#define LOGFILE LOGDIR"/submit.log"

extern int verbose;
extern int loglevel;

const int backlog = 32;  /* how many pending connections queue will hold */
const int linelen = 256; /* maximum length read from submit_db stdout lines */

/* Accepted characters in submission filenames (except for alphanumeric) */
const char filename_chars[5] = ".-_ ";

extern int errno;

char *progname;

int port = SUBMITPORT;

int show_help;
int show_version;
int inet4_only;
int inet6_only;

struct option const long_opts[] = {
	{"port",       required_argument, NULL,         'P'},
	{"verbose",    optional_argument, NULL,         'v'},
	{"help",       no_argument,       &show_help,    1 },
	{"version",    no_argument,       &show_version, 1 },
	{ NULL,        0,                 NULL,          0 }
};

/* server listen-socket filedescriptors (for each address) */
const int max_server_nfds = 2;
int server_nfds;
struct pollfd server_fds[max_server_nfds];

int  client_fd; /* client connection specific socket filedescriptor */
char client_addr[NI_MAXHOST]; /* string of client IP address */
struct sockaddr_storage client_sock; /* client socket information */
socklen_t socklen = sizeof(sockaddr_storage);

void version();
void usage();
void create_server();
int  handle_client();
void sigchld_handler(int);

int main(int argc, char **argv)
{
	struct sigaction sigchildaction;
	int child_pid;
	int exit_status;
	int c, i, err;
	char *ptr;
		
	progname = argv[0];

	/* Set logging levels & open logfile */
	verbose  = LOG_INFO;
	loglevel = LOG_DEBUG;
	stdlog   = fopen(LOGFILE,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",LOGFILE);

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"P:v:",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* this is a long-only option: nothing to do */
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || port<0 || port>65535 ) {
				error(0,"invalid TCP port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			if ( optarg!=NULL ) {
				verbose = strtol(optarg,&ptr,10);
				if ( *ptr!=0 || verbose<0 ) {
					error(0,"invalid verbosity specified: `%s'",optarg);
				}
			} else {
				verbose++;
			}
			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default: /* should not happen */
			error(0,"getopt returned character code `%c' ??",c);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();
	
	if ( argc>optind ) error(0,"non-option arguments given");

	logmsg(LOG_NOTICE,"server started [%s]", DOMJUDGE_PROGRAM);

	create_server();
	logmsg(LOG_INFO,"listening on port %d/tcp", port);
    
    /* Setup the child signal handler */
	sigchildaction.sa_handler = sigchld_handler;
	sigemptyset(&sigchildaction.sa_mask);
	sigchildaction.sa_flags = SA_RESTART;
	if ( sigaction(SIGCHLD,&sigchildaction,NULL)!=0 ) {
		error(errno,"setting child signal handler");
	}
	logmsg(LOG_DEBUG,"child signal handler installed");
	
    /* main accept() loop of incoming connections */
    while ( true ) {

		if ( poll(server_fds,server_nfds,-1)<0 ) {
			if ( errno==EINTR ) continue;
			error(errno,"polling socket(s)");
		}

		for(i=0; i<server_nfds; i++) {
			if ( !(server_fds[i].revents & POLLIN) ) continue;
		
			client_fd = accept(server_fds[i].fd,
			                   (struct sockaddr *) &client_sock,&socklen);
				
			if ( client_fd<0 ) {
				warning(errno,"accepting incoming connection");
				continue;
			}
        
			logmsg(LOG_INFO,"incoming connection, spawning child");
			
			switch ( child_pid = fork() ) {
			case -1: /* error */
				error(errno,"cannot fork");
				
			case  0: /* child thread */
				signal(SIGCHLD,SIG_DFL); /* child should not listen to signals */
				
				err = getnameinfo((struct sockaddr *) &client_sock,socklen,
				                  client_addr,sizeof(client_addr),NULL,0,NI_NUMERICHOST);
					
				if ( err!=0 ) error(0,"getnameinfo: %s",gai_strerror(err));
				
				logmsg(LOG_NOTICE,"connection from %s",client_addr);
				
				exit_status = handle_client();
				
				logmsg(LOG_INFO,"child exiting");
				switch ( exit_status ) {
				case SUCCESS: exit(SUCCESS_EXITCODE);
				case WARNING: exit(WARNING_EXITCODE);
				case FAILURE: exit(FAILURE_EXITCODE);
				}
				exit(FAILURE_EXITCODE); /* Shouldn't happen */

			default: /* parent thread */
				close(client_fd); /* parent doesn't need the client_fd */
				logmsg(LOG_DEBUG,"spawned child, pid=%d", child_pid);
			}
		}
	}

	return FAILURE; /* This should never be reached */
}

void version()
{
	printf("%s %s\nWritten by %s\n\n",DOMJUDGE_PROGRAM,PROGRAM,AUTHORS);
	printf(
"%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n"
"are welcome to redistribute it under certain conditions.  See the GNU\n"
"General Public Licence for details.\n",PROGRAM);
	exit(0);
}

void usage()
{
	printf(
"Usage: %s [OPTION]...\n"
"Start the submitserver.\n"
"\n"
"  -4, --inet4-only      only bind to IPv4 addresses\n"
"  -6, --inet6-only      only bind to IPv6 addresses\n"
"  -P, --port=PORT       set TCP port to listen on to PORT (default: %i)\n"
"  -v, --verbose=LEVEL   set verbosity to LEVEL (syslog levels)\n"
"      --help            display this help and exit\n"
"      --version         output version information and exit\n"
"\n",progname,port);
	
	exit(0);
}

/***
 *  Open listening socket(s) on this host.
 */
void create_server()
{
	struct addrinfo hints;
	struct addrinfo *res, *r;
	char *port_str;
	int err, fd;
	
	/* Set preferred network connection options: use both IPv4 and
	   IPv6 by default */ 
	memset(&hints, 0, sizeof(hints));
	hints.ai_family   = AF_INET;
	hints.ai_flags    = AI_PASSIVE | AI_ADDRCONFIG;
	hints.ai_socktype = SOCK_STREAM;
	hints.ai_protocol = IPPROTO_TCP;

	/* Get all (IPv4-only) addresses associated with us */
	port_str = allocstr("%d",port);
	if ( (err = getaddrinfo(NULL,port_str,&hints,&res)) ) {
		error(0,"getaddrinfo: %s",gai_strerror(err));
	}
	free(port_str);

	/* Try to open a socket for each local address */
	server_nfds = 0;
	for(r=res; r!=NULL && server_nfds<max_server_nfds; r=r->ai_next) {

		char server_addr[NI_MAXHOST];
		err = getnameinfo(r->ai_addr,r->ai_addrlen,server_addr,
						  sizeof(server_addr),NULL,0,NI_NUMERICHOST);
		if ( err!=0 ) error(0,"getnameinfo: %s",gai_strerror(err));
		
		logmsg(LOG_DEBUG,"trying to open listen socket on %s",server_addr);
		
		if ( (fd=socket(r->ai_family,r->ai_socktype,r->ai_protocol))<0 ) {
			error(errno,"opening socket");
		}
			
		/* setsockopt needs a pointer to the value of the option to be set */
		int optval = 1;
		if ( setsockopt(fd,SOL_SOCKET,SO_REUSEADDR,&optval,sizeof(int))!=0 ) {
			error(errno,"cannot set socket options");
		}

		if ( bind(fd,r->ai_addr,r->ai_addrlen)!=0 ) {
			/* Ignore address in use error: Linux does not allow
			   us to bind to an IPv4 and IPv6 on the same port. */
			if ( errno==EADDRINUSE ) {
				logmsg(LOG_DEBUG,"not listening on %s: address in use",
				       server_addr);
				close(fd);
				continue;
			} else {
				error(errno,"binding server socket");
			}
		}

		if ( listen(fd,backlog)!=0 ) {
			error(errno,"starting listening");
		}
		
		/* Store successfully opened listen socket in server_fds */
		server_fds[server_nfds].fd = fd;
		server_fds[server_nfds].events = POLLIN;
		server_nfds++;
		logmsg(LOG_INFO,"listening on %s",server_addr);
	}
	
	freeaddrinfo(res);
	if ( server_nfds==0 ) {
		error(0,"could not create server socket(s)");
	}
}

/***
 *  Talk with a client: receive submission information and copy source-file.
 */
int handle_client()
{
	string command, argument;
	string team, problem, language, filename;
	char *fromfile, *tempfile, *tmp, *tmp2;
	struct passwd *userinfo;
	char *args[MAXARGS];
	int redir_fd[3];
	int status;
	pid_t cpid;
	FILE *rpipe;
	char line[linelen];
	int i;
	
	sendit(client_fd,"+server ready");

	while ( receive(client_fd) ) {

		// Make sure that tmp is big enough to contain command and argument
		tmp2 = tmp = allocstr("%s",lastmesg);
		
		strsep(&tmp2," ");
		
		command.erase();
		argument.erase();
		
		if ( tmp !=NULL ) command  = string(tmp);
		if ( tmp2!=NULL ) argument = string(tmp2);
		
		free(tmp);
	
		command = stringtolower(command);
		if ( command=="team" ) {
			team = argument;
			sendit(client_fd,"+received team '%s'",argument.c_str());
		} else
		if ( command=="problem" ) {
			problem = stringtolower(argument);
			sendit(client_fd,"+received problem '%s'",argument.c_str());
		} else
		if ( command=="language" ) {
			language = stringtolower(argument);
			sendit(client_fd,"+received language '%s'",argument.c_str());
		} else
		if ( command=="filename" ) {
			filename = argument;
			sendit(client_fd,"+received filename '%s'",argument.c_str());
		} else 
		if ( command=="quit" ) {
			logmsg(LOG_NOTICE,"received quit, aborting");
			close(client_fd);
			return FAILURE;
		} else 
		if ( command=="done" ) {
			break;
		} else {
			senderror(client_fd,0,"invalid command: '%s'",command.c_str());
		}
	}
	
	if ( problem.empty()  || team.empty() ||
	     language.empty() || filename.empty() ) {
		senderror(client_fd,0,"missing submission info");
	}

	logmsg(LOG_NOTICE,"submission received: %s/%s/%s",
	       team.c_str(),problem.c_str(),language.c_str());

	/* Create the absolute path to submission file, which is expected
	   (and for security explicitly taken) to be basename only! */
	filename = string(gnu_basename(filename.c_str()));

	for(i=0; i<(int)filename.length(); i++) {
		if ( !( isalnum(filename[i]) || strchr(filename_chars,filename[i]) ) )
			senderror(client_fd,0,"illegal character '%c' in filename",filename[i]);
	}
	
	if ( (userinfo = getpwnam(team.c_str()))==NULL ) {
		senderror(client_fd,0,"cannot find team username");
	}

	fromfile = allocstr("%s/%s/%s",userinfo->pw_dir,USERSUBMITDIR,
	                    filename.c_str());
	
	tempfile = allocstr("%s/cmdsubmit.%s.%s.XXXXXX.%s",INCOMINGDIR,
	                    problem.c_str(),team.c_str(),language.c_str());
	
	if ( mkstemps(tempfile,language.length()+1)<0 || strlen(tempfile)==0 ) {
		senderror(client_fd,errno,"mkstemps cannot create tempfile");
	}
	
	logmsg(LOG_INFO,"created tempfile: `%s'",gnu_basename(tempfile));
	
	/* Copy the source-file */
	args[0] = (char *) team.c_str();
	args[1] = fromfile;
	args[2] = tempfile;
	redir_fd[0] = redir_fd[1] = redir_fd[2] = 0;
	switch ( (status = execute("./submit_copy.sh",args,3,redir_fd,1)) ) {
	case  0: break;
	case -1: senderror(client_fd,errno,"starting submit_copy");
	case -2: senderror(client_fd,0,"starting submit_copy: internal error");
	default: senderror(client_fd,0,"submit_copy failed with exitcode %d",status);
	}
	
	logmsg(LOG_INFO,"copied `%s' to tempfile",filename.c_str());
	
	/* Check with database for correct parameters
	   and then add a database entry for this file. */
	args[0] = (char *) team.c_str();
	args[1] = client_addr;
	args[2] = (char *) problem.c_str();
	args[3] = (char *) language.c_str();
	args[4] = gnu_basename(tempfile);
	redir_fd[0] = 0;
	redir_fd[1] = 1;
	redir_fd[2] = 0;
	if ( (cpid = execute("./submit_db.php",args,5,redir_fd,1))<0 ) {
		senderror(client_fd,errno,"starting submit_db");
	}

	if ( (rpipe = fdopen(redir_fd[1],"r"))==NULL ) {
		senderror(client_fd,errno,"binding submit_db stdout to stream");
	}
	
	/* Read stdout/stderr and try to find errors */
	while ( fgets(line,linelen,rpipe)!=NULL ) {

		/* Remove newlines from end of line */
		i = strlen(line)-1;
		while ( i>=0 && (line[i]=='\n' || line[i]=='\r') ) line[i--] = 0;

		fprintf(stderr,"%s\n",line);
		
		/* Search line for error/warning messages */
		if ( (tmp = strstr(line,ERRMATCH))!=NULL ) {
			senderror(client_fd,0,"%s",&tmp[strlen(ERRMATCH)]);
		}
		if ( (tmp = strstr(line,WARNMATCH))!=NULL ) {
			sendwarning(client_fd,0,"%s",&tmp[strlen(WARNMATCH)]);
			return WARNING;
		}
	}

	if ( fclose(rpipe)!=0 ) {
		senderror(client_fd,errno,"closing submit_db pipe");
	}
	
	if ( waitpid(cpid,&status,0)<0 ) {
		senderror(client_fd,errno,"waiting for submit_db");
	}
	
	if ( WIFEXITED(status) && WEXITSTATUS(status)!=0 ) {
		senderror(client_fd,0,"submit_db failed with exitcode %d",
		          WEXITSTATUS(status));
	}

	if ( ! WIFEXITED(status) ) {
		if ( WIFSIGNALED(status) ) {
			senderror(client_fd,0,"submit_db terminated with signal %d",
					  WTERMSIG(status));
		}
		if ( WIFSTOPPED(status) ) {
			senderror(client_fd,0,"submit_db stopped with signal %d",
					  WSTOPSIG(status));
		}
		senderror(client_fd,0,"submit_db aborted due to unknown error");
	}
	
	logmsg(LOG_INFO,"added submission to database");
	
	if ( unlink(tempfile)!=0 ) error(errno,"deleting tempfile");

	sendit(client_fd,"+done submission successful");
	close(client_fd);
	
	return SUCCESS;
}

/***
 *  Watch and report termination of child threads
 */
void sigchld_handler(int sig)
{
	pid_t pid;
	int exitcode;
	
	pid = waitpid(0,&exitcode,WNOHANG);

	if ( pid<=0 ) {
		/* Don't react on "No child processes" or looping will occur
		   due to childs from 'system' call */
		if ( errno==ECHILD ) return;
		warning(errno,"waiting for child, pid = %d, exitcode = %d",pid,exitcode);
		system(SYSTEM_ROOT"/bin/beep "BEEP_ERROR" &");
		return;
	}
	
	logmsg(LOG_INFO,"child process %d exited with exitcode %d",pid,exitcode);

	/* Audibly report submission status with beeps */
	switch ( exitcode ) {
	case SUCCESS_EXITCODE:
		system(BEEP_CMD" "BEEP_SUBMIT" &");
		break;

	case WARNING_EXITCODE:
		system(BEEP_CMD" "BEEP_WARNING" &");
		break;

	case FAILURE_EXITCODE:
	default:
		system(BEEP_CMD" "BEEP_ERROR" &");
	}
}

//  vim:ts=4:sw=4:
