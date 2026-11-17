'use strict';
const addQuestion=document.querySelector('#add-question');
const questionList=document.querySelector('#question-list');
if(addQuestion&&questionList){
 addQuestion.hidden=false;
 addQuestion.addEventListener('click',()=>{
  if(questionList.children.length>=20){addQuestion.textContent='Maximum 20 rows; save to discard unused blank rows.';return;}
  const source=questionList.lastElementChild;
  if(!source)return;
  const clone=source.cloneNode(true);
  const previous=clone.querySelector('input[type=hidden]').value;
  const key='q'+Date.now().toString(16)+Math.random().toString(16).slice(2,7);
  clone.querySelector('legend').textContent='New question';
  for(const element of clone.querySelectorAll('input,select,textarea,label')){
   for(const attribute of ['name','id','for']){if(element.hasAttribute(attribute))element.setAttribute(attribute,element.getAttribute(attribute).replaceAll(previous,key));}
   if(element.tagName==='LABEL')continue;
   if(element.type==='hidden')element.value=key;
   else if(element.type==='checkbox')element.checked=false;
   else if(element.tagName==='SELECT')element.value='text';
   else if(element.type==='number')element.value=questionList.children.length*10;
   else element.value='';
  }
  questionList.append(clone);clone.querySelector('input:not([type=hidden])').focus();
 });
}
