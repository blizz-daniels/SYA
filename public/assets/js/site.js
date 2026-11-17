'use strict';
const button = document.querySelector('.menu-toggle');
const nav = document.querySelector('#navigation');
if (button && nav) {
 button.hidden = false;
 document.documentElement.classList.add('js');
 const close = () => { button.setAttribute('aria-expanded', 'false'); nav.classList.remove('open'); };
 button.addEventListener('click', () => { const open = button.getAttribute('aria-expanded') !== 'true'; button.setAttribute('aria-expanded', String(open)); nav.classList.toggle('open', open); });
 document.addEventListener('keydown', event => { if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') { close(); button.focus(); } });
 nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
 window.matchMedia('(min-width: 1100px)').addEventListener('change', close);
}
