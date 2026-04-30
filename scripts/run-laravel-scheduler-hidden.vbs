Set shell = CreateObject("WScript.Shell")
command = "cmd /c cd /d ""C:\xampp\htdocs\goldapp"" && ""C:\xampp\php\php.exe"" artisan schedule:run"
shell.Run command, 0, True
