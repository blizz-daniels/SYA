'use strict';
document.querySelectorAll('.share-story').forEach(button=>{
 if(!navigator.share&&!navigator.clipboard?.writeText)return;
 button.hidden=false;
 button.addEventListener('click',async()=>{
  const url=new URL(button.dataset.url,location.href).href;
  const status=button.closest('.share-controls').querySelector('[role=status]');
  try{if(navigator.share){await navigator.share({title:button.dataset.title,url});status.textContent='Share options opened.';}else{await navigator.clipboard.writeText(url);status.textContent='Post link copied.';}}
  catch(error){status.textContent=error.name==='AbortError'?'Sharing cancelled.':'Use the post link to share this page.';}
 });
});
