package main

import (
	"fmt"
	"math/rand"
	"sync"
)


func main()  {
	c := sync.NewCond(&sync.Mutex{})
	queueList := make(chan int, 10)

	//producer
	go func() {
		for {
			c.L.Lock()

			if len(queueList) == 5 {
				c.Wait()
			}

			num := rand.Intn(100)

			fmt.Println("Producer:", num)
			queueList <- num

			if len(queueList) == 5 {
				c.Signal()
			}
			c.L.Unlock()
		}
	}()

	//consumer
	go func() {
		for {
			c.L.Lock()

			if len(queueList) == 0 {
				c.Wait()
			}

			num := <- queueList

			fmt.Println("consumer:", num)

			if len(queueList) == 0 {
				c.Signal()
			}

			c.L.Unlock()
		}
	}()

	for {
		;
	}
}

