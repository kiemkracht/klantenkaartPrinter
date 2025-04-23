Set WshShell = CreateObject("WScript.Shell")

Do
    WshShell.Run """C:\pdf versie\KiemkrachtPrintAgent\run_php.bat""", 0, True
    WScript.Sleep 30000 ' 30 seconds
Loop
