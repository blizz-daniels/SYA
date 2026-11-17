
'use strict';
const hex=bytes=>Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');
document.querySelector('#generate').addEventListener('click',async()=>{
 try{
  if(location.protocol!=='https:'&&location.protocol!=='file:'&&location.hostname!=='127.0.0.1'&&location.hostname!=='localhost')throw new Error('Open this helper over HTTPS before generating secrets.');
  if(!globalThis.crypto?.subtle)throw new Error('This browser does not provide Web Crypto for local files. Use the CLI instructions or a browser that supports it.');
  const token=hex(crypto.getRandomValues(new Uint8Array(32)));
  const key=hex(crypto.getRandomValues(new Uint8Array(32)));
  const hash=hex(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(token))));
  const code='Sya-'+btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(32)))).replaceAll('+','-').replaceAll('/','_').replaceAll('=','');
  for(const [id,value]of Object.entries({app_key:key,token_hash:hash,token,code}))document.getElementById(id).value=value;
  document.querySelector('#values').hidden=false;
  document.querySelector('#status').textContent='Values generated locally. Select each field to copy it.';
 }catch(error){document.querySelector('#status').textContent=error.message;}
});
