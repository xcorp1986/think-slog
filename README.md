# think-slog
useful debug tool base on socket log

### REQUIREMENT
* nodejs
* chrome extension(file 'chrome.crx' in resource folder)
* additional port 1229 and port 1116 is required,please check your firewall and add it in whitelist

### HOWTO
> install socketlog-server and run it
```
npm install -g socketlog-server
```
```
socketlog-server
```
> debug like follow in php where you want
```
    some code...;
    slog('whatever you want');
```

###### then open chrome dev-tool,you should see debug output in console tab ^_^