'use strict';
(() => {
 const dialog=document.querySelector('.media-viewer');
 const links=[...document.querySelectorAll('.gallery-image')];
 if(!dialog||typeof dialog.showModal!=='function'||!links.length)return;
 const image=dialog.querySelector('#viewer_image'),caption=dialog.querySelector('#viewer_caption'),position=dialog.querySelector('#viewer_position');
 let current=0,opener=null,start=null;
 const show=index=>{current=(index+links.length)%links.length;const link=links[current];image.src=link.href;image.alt=link.querySelector('img').alt;caption.textContent=link.dataset.caption;position.textContent=`${current+1} of ${links.length}`;};
 links.forEach((link,index)=>link.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();opener=link;show(index);dialog.showModal();dialog.querySelector('[data-viewer-close]').focus();}));
 dialog.querySelector('[data-viewer-close]').addEventListener('click',()=>dialog.close());
 dialog.querySelector('[data-viewer-prev]').addEventListener('click',()=>show(current-1));dialog.querySelector('[data-viewer-next]').addEventListener('click',()=>show(current+1));
 dialog.addEventListener('keydown',event=>{if(event.key==='ArrowRight'){event.preventDefault();show(current+1);}if(event.key==='ArrowLeft'){event.preventDefault();show(current-1);}});
 dialog.addEventListener('close',()=>{image.removeAttribute('src');if(opener)opener.focus();});
 image.addEventListener('touchstart',event=>{start=event.touches[0]?.clientX??null;},{passive:true});
 image.addEventListener('touchend',event=>{if(start===null)return;const delta=(event.changedTouches[0]?.clientX??start)-start;if(Math.abs(delta)>60)show(current+(delta<0?1:-1));start=null;},{passive:true});
})();
