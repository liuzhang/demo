#include<stdio.h>
#include<stdlib.h>
#include<unistd.h>
#include<errno.h>
#include<string.h>
#include<sys/types.h>
#include<netinet/in.h>
#include<sys/socket.h>
#include<sys/wait.h>

#define PORT 9527 //端口号
#define BACKLOG 5 //最大监听数
#define MAXLINE 100 

int main()
{
	int sockfd, new_fd;
	struct sockaddr_in server_addr, client_addr;
	int sin_size, len;
	char buf[MAXLINE];
	
	sockfd = socket(AF_INET, SOCK_STREAM, 0);
	
	if (sockfd == -1)
	{
		printf("socket failed:%d", errno);
		return -1;
	}
	
	server_addr.sin_family = AF_INET;
	server_addr.sin_port = htons(PORT);
	server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	bzero(&(server_addr.sin_zero), 8); //将其他属性设置 0
	
	/* bind */
	
	if (bind(sockfd, (struct sockaddr*)&server_addr, sizeof(struct sockaddr)) < 0)
	{
		printf("bind error");
		return -1;
	}
	
	/* listen */
	listen(sockfd, BACKLOG);
	
	while (1)
	{
		sin_size = sizeof(client_addr);
		
		new_fd = accept(sockfd, (struct sockaddr*)&client_addr, &sin_size);
		
		if (new_fd == -1)
		{
			printf("receive failed");
			
			return -1;
		} else
		{
			printf("receive success \n");
			len = recv(new_fd, buf, MAXLINE, 0);
            printf("***Client***    %s \n", buf);
			send(new_fd, buf, len, 0);
		}
	}
	
	return 0;
}

