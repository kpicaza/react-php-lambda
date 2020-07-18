# React PHP AWS Lambda

> Proof of Concept of Running React PHP in aws lambda server

A proof of concept on how to run React-php on a serverless application at AWS Lambda service.

## Contributors

* [xserrat](https://github.com/xserrat)
* [kpicaza](https://github.com/kpicaza)

## System Requirements

* AWS Console with configured access
* Serverless Framework

## Goals

[x] Execute an Async PRS-7 Request Handler from PSR-11 container
[] Execute callable class from PSR-11 container
[] Execute complete application like DriftPHP from an unique endpoint

## Especial thanks

* [mnapoli](https://github.com/mnapoli) For making Bref, opening a new world of possibilities for PHP
* [mmoreram](https://github.com/mmoreram) For giving us him's point of view.


## Workaround

* Use Bref PHP-FPM 7.4 Layer
* We add a custom bootstrap file using a React PHP event loop instead of the "while=true" loop given by default Bref bootstrap.
* The given handler is managed with promises by the [ReactHandler]() class 
* We replace the [LambdaRuntime]() class by [ReactRuntime]() 

## Captures

Using Antidot Framework application's container, event dispatcher and Request handler returning promises.
 
![Logs](images/first-functional-log.png) 
