# bench/async/echo.aw -- the echo server of echod.aw and ping.aw: a task
# that accepts connections on a listening socket and one task for each
# connection, which sends back what it reads until the other side closes
async fn echoconn c
	local b 4096
	while 1
		await asyncread c b 4096 as n
		if n-1<4096
			await asyncwriteall c b n as n
		end
		if n-1>=4096
			call asyncclose c
			ret 0
		end
	end
end
async fn echoserve l
	while 1
		await asyncaccept l as c
		if c>-4096
			ret c
		end
		call echoconn c as k
		call asyncdetach k
	end
end
