"""HTTP integration fixture for an isolated localhost WordPress, using synthetic data only."""
import base64
import json
import os
import secrets
from urllib.parse import urlparse, parse_qs, urlencode, urlunparse
import requests
from bs4 import BeautifulSoup

base = os.environ.get('KTICKETS_BASE', 'http://localhost:8099/konstantinos')
admin = requests.Session()
admin.get(base + '/wp-login.php').raise_for_status()
r = admin.post(base + '/wp-login.php', data={'log':'fixture_owner','pwd':'fixture-password-only','wp-submit':'Log In','testcookie':'1','redirect_to':base+'/wp-admin/'})
assert 'wordpress_logged_in' in ';'.join(admin.cookies.keys()), 'fixture login failed'
r = admin.get(base + '/wp-admin/options-general.php?page=ktickets-setup')
soup = BeautifulSoup(r.text,'html.parser')
setup = soup.find('input', {'name':'action','value':'ktickets_setup'})
assert setup, 'fixture must start with no ticket owner'
form = setup.find_parent('form')
nonce = form.find('input',{'name':'_wpnonce'})['value']
assert admin.post(base + '/wp-admin/admin-post.php', data={'action':'ktickets_setup','owner':'1','_wpnonce':'invalid'}).status_code==403
r = admin.post(base + '/wp-admin/admin-post.php', data={'action':'ktickets_setup','owner':'1','_wpnonce':nonce})
r.raise_for_status()
soup = BeautifulSoup(r.text,'html.parser')
form = soup.find('input', {'name':'action','value':'ktickets_settings'}).find_parent('form')
settings = {i['name']:i.get('value','') for i in form.find_all('input') if i.get('name') and i.get('type')!='checkbox'}
settings.update({'verified':'on','enabled':'on','policy_url':'https://example.test/privacy'})
assert admin.post(base + '/wp-admin/admin-post.php', data={**settings,'_wpnonce':'invalid'}).status_code==403
assert admin.post(base + '/wp-admin/admin-post.php', data={'action':'ktickets_project','slug':'bad-fixture','name':'Bad fixture','categories[]':'technical','_wpnonce':'invalid'}).status_code==403
r = admin.post(base + '/wp-admin/admin-post.php', data=settings)
r.raise_for_status()
guest = requests.Session()
r = guest.get(base + '/?page_id=' + settings['form_page'])
r.raise_for_status()
assert 'no-store' in r.headers['Cache-Control']
assert r.headers['Referrer-Policy']=='no-referrer'
assert "default-src 'none'" in r.headers['Content-Security-Policy']
soup=BeautifulSoup(r.text,'html.parser')
assert not soup.find_all('script',src=lambda src:src and not src.startswith(base))
assert len(soup.find_all('script'))==1
c=json.loads(soup.find(attrs={'data-kt-config':True})['data-kt-config'])
origin=urlparse(base).scheme+'://'+urlparse(base).netloc

def api(path, data=None, session=guest, headers=None, method=None):
    u=urlparse(c['api']); query=parse_qs(u.query)
    if 'rest_route' in query:
        query['rest_route']=[query['rest_route'][0].rstrip('/')+'/'+path]
        url=urlunparse(u._replace(query=urlencode(query,doseq=True)))
    else:
        url=c['api'].rstrip('/')+'/'+path
    return session.request(method or ('POST' if data is not None else 'GET'),url,json=data,headers={'Origin':origin,**(headers or {})})

f=api('form',{'project':'soft1-code-assistant','privacy':False});f.raise_for_status()
secret=base64.urlsafe_b64encode(secrets.token_bytes(32)).decode().rstrip('=')
d={'project':'soft1-code-assistant','category':'technical','subject':'HTTP synthetic fixture','body':'Plain <script>alert(1)</script> Ελληνικά 🦉 code and one https://example.test URL.','alias':'Synthetic only','acknowledge':True,'secret':secret,'idempotency':base64.urlsafe_b64encode(secrets.token_bytes(24)).decode().rstrip('='),'form_token':f.json()['token']}
r=api('tickets',d);r.raise_for_status();receipt=r.json()
r2=api('tickets',d);r2.raise_for_status();assert r2.json()['public_id']==receipt['public_id']
exchange=api('access/exchange',{'ticket':receipt['public_id'],'key':secret});exchange.raise_for_status();csrf=exchange.json()['csrf']
cookie=exchange.headers['Set-Cookie'];assert 'HttpOnly' in cookie and 'SameSite=Strict' in cookie and 'path=/konstantinos/' in cookie
view=api('tickets/'+receipt['public_id']);view.raise_for_status();dto=view.json()
assert dto['messages'][0]['body']==d['body']
assert 'no-store' in view.headers['Cache-Control']
assert 'Access-Control-Allow-Credentials' not in view.headers
assert api('tickets/'+receipt['public_id'],headers={'Origin':'https://evil.example.test'}).status_code==403
assert api('tickets/'+receipt['public_id']+'/messages',{'revision':dto['revision'],'body':'Reply without CSRF'}).status_code==403
reply=api('tickets/'+receipt['public_id']+'/messages',{'revision':dto['revision'],'body':'Synthetic HTTP reply.'},headers={'X-KT-CSRF':csrf});reply.raise_for_status()
view=api('tickets/'+receipt['public_id']);assert view.json()['messages'][-1]['body']=='Synthetic HTTP reply.'
portal=guest.get(receipt['portal_url']);portal.raise_for_status();assert 'no-store' in portal.headers['Cache-Control']
privacy=guest.get(base+'/?page_id='+settings['privacy_page']);privacy.raise_for_status();ps=BeautifulSoup(privacy.text,'html.parser');assert ps.find('select',{'name':'request_type'});assert not ps.find('input',{'name':'soft1_version'})
logout=api('access/logout',{'ticket':receipt['public_id']},headers={'X-KT-CSRF':csrf});logout.raise_for_status();assert api('tickets/'+receipt['public_id']).status_code==403
# Admin REST uses a separate WP nonce and checks object capability.
html=admin.get(base+'/wp-admin/admin.php?page=ktickets');html.raise_for_status();assert 'kt-admin-note' in html.text
ac=json.loads(BeautifulSoup(html.text,'html.parser').find(attrs={'data-kt-config':True})['data-kt-config'])
r=api('admin/tickets',session=admin,headers={'X-WP-Nonce':ac['nonce']});r.raise_for_status();assert len(r.json())==1
assert api('admin/tickets').status_code==403
# Explicit owner transfer is available in Settings and survives loss of ticket capabilities.
settingsPage=admin.get(base+'/wp-admin/options-general.php?page=ktickets-settings');settingsPage.raise_for_status()
ownerForm=BeautifulSoup(settingsPage.text,'html.parser').find('input',{'name':'action','value':'ktickets_owner'}).find_parent('form')
choices=ownerForm.find('select',{'name':'owner'}).find_all('option')
target=next(o['value'] for o in choices if o.text.strip()=='fixture_agent')
ownerNonce=ownerForm.find('input',{'name':'_wpnonce'})['value'];oldOwner=ownerForm.find('input',{'name':'expected_owner'})['value']
transfer={'action':'ktickets_owner','owner':target,'expected_owner':oldOwner,'confirm_transfer':'on','_wpnonce':ownerNonce}
assert admin.post(base+'/wp-admin/admin-post.php',data={**transfer,'_wpnonce':'invalid'}).status_code==403
assert admin.post(base+'/wp-admin/admin-post.php',data={k:v for k,v in transfer.items() if k!='confirm_transfer'}).status_code==400
moved=admin.post(base+'/wp-admin/admin-post.php',data=transfer);moved.raise_for_status();assert 'ktickets-settings' in moved.url
assert api('admin/tickets',session=admin,headers={'X-WP-Nonce':ac['nonce']}).status_code==403
retained=admin.get(base+'/wp-admin/options-general.php?page=ktickets-settings');retained.raise_for_status()
backForm=BeautifulSoup(retained.text,'html.parser').find('input',{'name':'action','value':'ktickets_owner'}).find_parent('form')
assert backForm.find('select',{'name':'owner'}).find('option',selected=True)['value']==target
newOwner=requests.Session();newOwner.get(base+'/wp-login.php').raise_for_status()
newOwner.post(base+'/wp-login.php',data={'log':'fixture_agent','pwd':'fixture-agent-only','wp-submit':'Log In','testcookie':'1','redirect_to':base+'/wp-admin/'})
newSettings=newOwner.get(base+'/wp-admin/options-general.php?page=ktickets-settings');newSettings.raise_for_status();assert 'Change ticket owner' in newSettings.text
restored=admin.post(base+'/wp-admin/admin-post.php',data={'action':'ktickets_owner','owner':oldOwner,'expected_owner':target,'confirm_transfer':'on','_wpnonce':backForm.find('input',{'name':'_wpnonce'})['value']});restored.raise_for_status()
assert api('admin/tickets',session=admin,headers={'X-WP-Nonce':ac['nonce']}).status_code==200
assert newOwner.get(base+'/wp-admin/options-general.php?page=ktickets-settings').status_code==403
print('PASS HTTP: explicit owner setup, disabled-to-enabled settings, isolated form/portal, headers/CSP, subdirectory cookies, create/retry/exchange/reply/logout, CSRF denial, privacy form and admin REST.')
