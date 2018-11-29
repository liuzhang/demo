package main

import (
	"io/ioutil"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"os"
	"regexp"
	"strings"
	"time"
)

var cookies []*http.Cookie

const (
	login_url  = "https://www.yiichina.com/login"
	sign_url = "https://www.yiichina.com/registration"
)


func main() {

	/******************** 登录开始 ****************************/
	c := &http.Client{}
	csrf := getCsrf(c, login_url, true)

	var postData = url.Values{}

	postData.Add("_csrf", csrf)
	postData.Add("LoginForm[username]", "liuzhang")
	postData.Add("LoginForm[password]", "lz19850610")
	postData.Add("LoginForm[rememberMe]", "0")
	postData.Add("LoginForm[rememberMe]", "1")
	postData.Add("login-button", "")
	postDataStr := postData.Encode()

	loginReq, _ := http.NewRequest("POST", login_url, strings.NewReader(postDataStr))

	loginReq.Header.Set("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8")
	loginReq.Header.Set("Accept-Language", "zh-CN,zh;q=0.9,en;q=0.8")
	loginReq.Header.Set("Cache-Control", "no-cache")
	loginReq.Header.Set("Connection","keep-alive")
	loginReq.Header.Set("Content-Length","250")
	loginReq.Header.Set("Content-Type","application/x-www-form-urlencoded")
	loginReq.Header.Set("Host","www.yiichina.com")
	loginReq.Header.Set("Origin", "https://www.yiichina.com")
	loginReq.Header.Set("Pragma", "no-cache")
	loginReq.Header.Set("Referer", "https://www.yiichina.com/login")
	loginReq.Header.Set("Upgrade-Insecure-Requests", "1")
	loginReq.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36")

	for _, v := range cookies {
		loginReq.AddCookie(v)
	}
	jar, _ := cookiejar.New(nil)
	c.Jar = jar
	resp, _ := c.Do(loginReq)

	cookies = resp.Cookies()

	/******************** 签到开始 ****************************/

	_csrf := getCsrf(c, sign_url, false)


	var signPostData = url.Values{}
	signPostData.Add("_csrf", _csrf)
	signPostStr := signPostData.Encode()

	signReq, _ := http.NewRequest("POST", sign_url, strings.NewReader(signPostStr))

	signReq.Header.Set("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8")
	signReq.Header.Set("Accept-Language", "zh-CN,zh;q=0.9,en;q=0.8")
	signReq.Header.Set("Cache-Control", "no-cache")
	signReq.Header.Set("Connection","keep-alive")
	signReq.Header.Set("Content-Length","250")
	signReq.Header.Set("Content-Type","application/x-www-form-urlencoded")
	signReq.Header.Set("Host","www.yiichina.com")
	signReq.Header.Set("Origin", "https://www.yiichina.com")
	signReq.Header.Set("Pragma", "no-cache")
	signReq.Header.Set("Referer", "https://www.yiichina.com/")
	signReq.Header.Set("X-Requested-With", "XMLHttpRequest")
	signReq.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36")

	for _, v := range cookies {
		signReq.AddCookie(v)
	}

	res, _ := c.Do(signReq)
	content, _ := ioutil.ReadAll(res.Body)

	name := "E:/go/1.txt"

	str_time := time.Now().Format("2006-01-02 15:04:05")

	f, _ := os.OpenFile(name, os.O_WRONLY|os.O_APPEND, 0666)

	logStr := str_time + " : " + string(content) + "\n"

	f.Write([]byte(logStr))

}

func getCsrf(c * http.Client, url string, isSetCookie bool) string {

	req, _ := http.NewRequest("GET", url, nil)

	res, _ := c.Do(req)

	data, _ := ioutil.ReadAll(res.Body)

	re, _ := regexp.Compile("<meta name=\"csrf-token\" content=\"(.+)\">")

	submatch := re.FindSubmatch([]byte(data))

	if isSetCookie {
		cookies = res.Cookies()
	}

	csrf := string(submatch[1])

	return csrf
}