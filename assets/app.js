const menu=document.querySelector('.menu-button');menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));document.querySelector('nav').classList.toggle('open',open);menu.textContent=open?'×':'☰';});
function event(name,detail={}){window.dispatchEvent(new CustomEvent('stankotech:event',{detail:{name,...detail}}));}
document.querySelectorAll('a[href^="tel:"]').forEach(a=>a.addEventListener('click',()=>event('phone_click')));
document.querySelectorAll('[data-brand-filter]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-brand-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));document.querySelectorAll('[data-brand]').forEach(c=>c.hidden=b.dataset.brandFilter!=='all'&&c.dataset.brand!==b.dataset.brandFilter);const n=[...document.querySelectorAll('[data-brand]')].filter(x=>!x.hidden).length;document.querySelector('#filter-status').textContent=`Показано серий: ${n}`;}));
function equipmentText(){const root=document.querySelector('[data-product]');if(!root)return '';const panel=root.querySelector('.variant-panel:not([hidden])');if(!panel)return root.dataset.product;const power=panel.dataset.power;const source=document.querySelector('#source-preference')?.value||panel.dataset.source?.trim();return `${root.dataset.product}; ${panel.dataset.model}; ${power==='help'?'нужна помощь с мощностью':power+' Вт'}${source?'; источник: '+source:''}`;}
function updateEquipment(){const text=equipmentText();if(text)document.querySelectorAll('[name="equipment"]').forEach(e=>e.value=text);}
document.querySelectorAll('[data-power]').forEach(button=>{if(button.tagName!=='BUTTON')return;button.addEventListener('click',()=>{document.querySelectorAll('button[data-power]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));document.querySelectorAll('.variant-panel').forEach(p=>p.hidden=p.dataset.power!==button.dataset.power);document.querySelectorAll('[data-spec-power]').forEach(p=>p.hidden=p.dataset.specPower!==button.dataset.power);const selected=document.querySelector('.variant-panel:not([hidden])');const photo=document.querySelector('.product-visual img');if(photo&&selected?.dataset.image){photo.src=selected.dataset.image;photo.alt=selected.dataset.model+' — изображение оборудования';document.querySelector('.product-visual p').textContent=selected.dataset.photoNote;}updateEquipment();});});
document.querySelector('#source-preference')?.addEventListener('change',updateEquipment);updateEquipment();
document.querySelectorAll('[data-request]').forEach(a=>a.addEventListener('click',()=>{const field=document.querySelector('[name="task"]');if(field&&a.dataset.request)field.value=a.dataset.request;updateEquipment();event('quote_intent');}));
const initial=new URLSearchParams(location.search).get('task');if(initial){const field=document.querySelector('[name="task"]');if(field)field.value=initial.slice(0,1000);}
document.querySelectorAll('.request').forEach(form=>{
 const submit=form.querySelector('button[type=submit]');submit.disabled=false;
 let pending=false;
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(pending)return;
  const result=form.querySelector('.form-result');result.className='form-result';result.replaceChildren();
  updateEquipment();if(!form.reportValidity())return;
  const fd=new FormData(form);const contact=String(fd.get('contact')||'').trim();
  if(fd.get('website')){result.textContent='Не удалось отправить форму.';return;}
  if(!(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact)||/^\+?[\d\s()\-]{7,24}$/.test(contact)&&contact.replace(/\D/g,'').length>=7)){
   result.textContent='Укажите корректный email или телефон с кодом страны.';form.querySelector('[name=contact]').focus();return;
  }
  const file=form.querySelector('[name=photo]').files[0];
  if(file&&(!/^image\/(jpeg|png|webp)$/.test(file.type)||file.size>10*1024*1024)){
   result.textContent='Прикрепите фото JPG, PNG или WebP размером до 10 МБ.';return;
  }
  if(!file)fd.delete('photo');
  pending=true;submit.disabled=true;const original=submit.textContent;submit.textContent='Отправляем…';result.textContent='Передаём заявку…';
  const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),60000);
  try{
   const tokenReply=await fetch('/api/contact.php',{credentials:'same-origin',cache:'no-store',signal:controller.signal});
   const tokenData=await tokenReply.json();
   if(!tokenReply.ok||!tokenData.ok||!tokenData.token)throw new Error(tokenData.message||'Не удалось подготовить отправку.');
   fd.set('csrf',tokenData.token);
   const reply=await fetch('/api/contact.php',{method:'POST',body:fd,credentials:'same-origin',signal:controller.signal});
   const data=await reply.json();
   if(!reply.ok||!data.ok)throw new Error(data.message||'Почтовый сервер не подтвердил отправку.');
   result.textContent=data.message;event('form_submitted');
  }catch(err){
   result.className='form-result error';
   const note=document.createElement('p');note.textContent=err.name==='AbortError'?'Не удалось получить подтверждение отправки. Проверьте позже или свяжитесь с нами напрямую.':(err instanceof SyntaxError?'Не удалось получить ответ от сервера. Свяжитесь с нами напрямую.':err.message||'Не удалось отправить заявку.');result.append(note);
   const email=document.createElement('a');email.href='mailto:info@stankotech.by';email.textContent='Написать на info@stankotech.by';result.append(email);
  }finally{clearTimeout(timer);pending=false;submit.disabled=false;submit.textContent=original;}
 });
});

document.querySelectorAll('video').forEach(v=>v.addEventListener('play',()=>event('video_play')));

// First-screen task switcher: turns the hero into a direct continuation of a sales call.
(()=>{
 const root=document.querySelector('[data-task-switcher]');
 if(!root)return;
 const tasks={
  welding:{
   image:'/assets/haohai-weld.webp',
   alt:'Лазерный аппарат HAOHAI H 4 в 1',
   kicker:'HAOHAI / GOLD MARK',
   title:'Лазерная сварка и аппараты 4 в 1',
   description:'Сварка металла, очистка, вспомогательная резка и зачистка сварных швов — в зависимости от выбранной серии и комплектации.',
   meta:['HAOHAI · 2000 / 3000 Вт','ПНР и обучение включены'],
   href:'/catalog/laser-welding/',
   request:'Нужно КП на лазерную сварку 4 в 1'
  },
  continuous:{
   image:'/assets/haohai-cw.webp',
   alt:'Аппарат непрерывной лазерной очистки HAOHAI',
   kicker:'HAOHAI / GOLD MARK',
   title:'Непрерывная лазерная очистка',
   description:'Производительное удаление ржавчины, оксидных слоёв и покрытий. Подбираем мощность под поверхность, площадь и режим работы.',
   meta:['HAOHAI · 1500 / 2000 / 3000 Вт','Доставка по Беларуси'],
   href:'/catalog/continuous-cleaning/',
   request:'Нужно КП на аппарат непрерывной лазерной очистки'
  },
  pulse:{
   image:'/assets/haohai-pulse.webp',
   alt:'Импульсный аппарат лазерной очистки HAOHAI',
   kicker:'HAOHAI / GOLD MARK',
   title:'Импульсная лазерная очистка',
   description:'Контролируемое импульсное воздействие для задач, где важны состояние поверхности и гибкая настройка режима.',
   meta:['HAOHAI · 200 / 300 Вт JPT','Гарантия 12 месяцев'],
   href:'/catalog/pulsed-cleaning/',
   request:'Нужно КП на импульсный аппарат лазерной очистки'
  }
 };
 const panel=root.querySelector('.hero-task-panel');
 const image=root.querySelector('#hero-task-image');
 const mediaLink=root.querySelector('#hero-task-media-link');
 const kicker=root.querySelector('#hero-task-kicker');
 const title=root.querySelector('#hero-task-title');
 const description=root.querySelector('#hero-task-description');
 const meta=root.querySelector('#hero-task-meta');
 const link=root.querySelector('#hero-task-link');
 const quote=root.querySelector('#hero-task-quote');
 function selectTask(key,button){
  const item=tasks[key];if(!item)return;
  root.querySelectorAll('[data-task-tab]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
  panel.classList.add('is-changing');
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   image.src=item.image;image.alt=item.alt;mediaLink.href=item.href;
   kicker.textContent=item.kicker;title.textContent=item.title;description.textContent=item.description;
   meta.replaceChildren(...item.meta.map(value=>{const span=document.createElement('span');span.textContent=value;return span;}));
   link.href=item.href;quote.dataset.request=item.request;
   panel.classList.remove('is-changing');
   event('hero_task_selected',{task:key});
  }));
 }
 root.querySelectorAll('[data-task-tab]').forEach(button=>button.addEventListener('click',()=>selectTask(button.dataset.taskTab,button)));
})();

