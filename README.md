# think-slog
[![Build Status](https://travis-ci.org/xcorp1986/think-slog.svg?branch=master)](https://travis-ci.org/xcorp1986/think-slog)

<p>
useful debug tool base on socket log
</p>


### REQUIREMENT

* nodejs LTS is `recommend`
* chrome extension(file 'chrome.crx' in `resource` folder)
* additional port `1229` and port `1116` is required,please check your firewall and add it in whitelist


### INSTALL

* installation is very simply via [composer](https://getcomposer.org/)
```shell
    composer install cheukpang/think-slog --save
```


### HOWTO

> install socketlog-server and run it

```shell
npm install -g socketlog-server
```

>Run socketlog-server in windows CMD or Linux Shell

```shell
socketlog-server
```
> debug like follow in php where you want

```php
<?php
    //$some_code_here;
    slog('log whatever you want');
    //...
```


###### then open chrome dev-tool,you should see debug output in console tab ^_^


# Reference

- [SocketLog](https://github.com/luofei614/SocketLog)


# License

Apache-2.0