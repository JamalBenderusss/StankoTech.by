const menu=document.querySelector('.menu-toggle');menu?.addEventListener('click',()=>{const open=document.querySelector('nav').classList.toggle('open');menu.setAttribute('aria-expanded',String(open))});
function track(name,detail={}){window.dispatchEvent(new CustomEvent('stankotech:analytics',{detail:{event:name,...detail}}));}
document.querySelectorAll('a[href^="tel:"]').forEach(a=>a.addEventListener('click',()=>track('phone_click')));
const source=document.querySelector('[data-source]');function setSource(value){if(!source||![...source.options].some(o=>o.value===value))throw new Error('Недоступный вариант источника');source.value=value;const p=document.querySelector('[name=equipment]');if(p)p.value=source.dataset.product+'; пожелание по источнику: '+value;return {equipment:p?.value,confirmed:false};}source?.addEventListener('change',()=>setSource(source.value));
document.querySelectorAll('[data-quote]').forEach(a=>a.addEventListener('click',()=>track('quote_request',{product:a.dataset.quote})));
document.querySelectorAll('form.request').forEach(form=>{
const status=form.querySelector('.status'),button=form.querySelector('button[type="submit"]');
form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;
const contact=form.elements.contact,val=contact.value.trim();
if(!(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)||/^[+\d\s()\-]{7,25}$/.test(val))){contact.setCustomValidity('Укажите телефон или email');contact.reportValidity();return;}
button.disabled=true;status.textContent='Отправляем заявку…';
const fd=new FormData(form);fd.set('page',location.pathname);
try{const response=await fetch(form.action,{method:'POST',body:fd,headers:{Accept:'application/json'}});const result=await response.json();
if(!response.ok||!result.ok)throw new Error(result.message||'Отправка не выполнена.');
status.textContent='Заявка принята для отправки. Мы свяжемся с вами по указанному контакту.';track('request_sent');form.reset();
}catch(err){status.textContent=(err instanceof SyntaxError?'Сервис отправки недоступен.':err.message)+' Вы также можете написать на info@stankotech.by или позвонить +375 29 290 58 63.';}
finally{button.disabled=false;}});
form.elements.contact.addEventListener('input',()=>form.elements.contact.setCustomValidity(''));
});
if(document.modelContext?.registerTool&&source){try{Promise.resolve(document.modelContext.registerTool({name:'stage_source_preference',description:'Указать пожелание по источнику в форме запроса КП. Не отправляет запрос и не подтверждает совместимость.',inputSchema:{type:'object',properties:{source:{type:'string',enum:[...source.options].map(o=>o.value)}},required:['source'],additionalProperties:false},annotations:{readOnlyHint:false},execute:input=>{if(!input||typeof input.source!=='string')throw new Error('Требуется источник');return setSource(input.source)}})).catch(()=>{});}catch{}}
document.querySelectorAll('video[data-video]').forEach(v=>v.addEventListener('play',()=>track('video_play',{video:v.dataset.video})));
document.querySelectorAll('[data-video-product]').forEach(a=>a.addEventListener('click',()=>track('video_product_click')));
