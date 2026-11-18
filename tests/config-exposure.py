"""CLI-isolated malformed PHP config tests. Never replaces the real configuration."""
import pathlib, subprocess, tempfile, shutil, os
php=os.environ.get('PHP_BINARY','php')
with tempfile.TemporaryDirectory(prefix='sya-config-test-') as temp:
    root=pathlib.Path(temp)
    (root/'app').mkdir();(root/'config').mkdir()
    for name in ['bootstrap.php','database.php','maintenance.php']:
        shutil.copyfile(pathlib.Path('app')/name,root/'app'/name)
    shutil.copyfile('config/config.example.php',root/'config/config.example.php')
    secret='TEST_CONFIGURATION_MUST_NEVER_BE_PRINTED'
    for label,body in [('missing PHP tag',secret),('unexpected output',"<?php echo '"+secret+"'; return [];"),('parse error',"<?php return [ '"+secret+"' => ;"),('invalid return type','<?php return 1;')]:
        (root/'config/config.php').write_text(body)
        r=subprocess.run([php,str(root/'app/bootstrap.php')],capture_output=True,text=True)
        assert r.returncode!=0 and secret not in r.stdout+r.stderr and not r.stdout,(label,r.returncode)
        print('PASS config fails closed without secret output:',label)
    (root/'config/config.php').unlink()
    r=subprocess.run([php,str(root/'app/bootstrap.php')],capture_output=True,text=True)
    assert r.returncode==0
    print('PASS missing private config uses safe public example')
