"""Run against Apache serving the whole project: python tests/hosting.py URL."""
import sys
import urllib.error
import urllib.request

base = sys.argv[1].rstrip('/')
for path in ['/config/config.php', '/config/config.example.php', '/app/bootstrap.php',
             '/database/migrations/001_foundation.sql', '/resources/views/pages.php',
             '/bin/migrate.php', '/tests/browser.cjs', '/docs/browser-results.txt',
             '/storage/private/access-check.txt', '/database/upgrade-milestone-3.sql', '/public/setup.php.dist', '/tools/setup-helper.html', '/README.md', '/AGENTS.md', '/.git/config', '/.htaccess', '/public/.htaccess']:
    try:
        response = urllib.request.urlopen(base + path)
        code = response.status
    except urllib.error.HTTPError as error:
        code = error.code
    assert code == 403, (path, code)
    print('PASS private URL denied:', path)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

try:
    urllib.request.build_opener(NoRedirect).open(base + '/index.php?page=characters')
    raise AssertionError('Expected redirect')
except urllib.error.HTTPError as error:
    assert error.code == 302
    assert error.headers['Location'] == 'public/index.php?page=characters'
print('PASS root redirect preserves page selection')
for page in ['home', 'about', 'characters', 'casting', 'crew', 'updates', 'media', 'contact']:
    response = urllib.request.urlopen(base + '/public/index.php?page=' + page)
    html = response.read().decode()
    assert response.status == 200
    assert '/public/assets/css/site.css' in html
    assert '/public/index.php?page=' in html
    print('PASS public page:', page)
for path in ['/', '/public/assets/css/site.css', '/public/assets/js/site.js', '/public/assets/images/silhouette.svg']:
    assert urllib.request.urlopen(base + path).status == 200
print('PASS root landing page and public assets')
