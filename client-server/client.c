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
#define MAXLINE 100
#define IP "127.0.0.1"

int main()
{
	int sockfd, new_fd;
	struct sockaddr_in server_addr;
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
	server_addr.sin_addr.s_addr = inet_addr(IP);
	bzero(&(server_addr.sin_zero), 8); //将其他属性设置 0
	
	if (connect(sockfd, (struct sockaddr*)&server_addr, sizeof(struct sockaddr)) <= -1)
	{
		printf("connect fail \n");
	} else
	{
		printf("Enter string to send: \n");  
        fgets(buf, MAXLINE, stdin);
        len=send(sockfd, buf, strlen(buf),0); 
        len=recv(sockfd, buf, MAXLINE, 0);  
        printf("received from server :%s\n", buf);  
	}		
	close(sockfd);

	return 0;
}

