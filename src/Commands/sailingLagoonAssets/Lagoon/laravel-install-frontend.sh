#!/bin/sh

echo Installing Frontend
npm install
npm ci
npm run build
