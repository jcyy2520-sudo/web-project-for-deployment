@echo off
cd web-frontend
set NODE_OPTIONS=--max-old-space-size=4096
npm run dev
pause
