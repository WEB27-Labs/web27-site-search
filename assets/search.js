(function(){
  'use strict';
  var strings=Web27SiteSearch.strings;
  function esc(s){var d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML.replace(/"/g,'&quot;');}
  function pageKey(value){
    try{
      if(typeof value!=='string'||!/^https?:\/\//i.test(value))return '';
      var url=new URL(value,document.baseURI);
      if(!/^https?:$/.test(url.protocol)||url.username||url.password||url.origin!==new URL(Web27SiteSearch.siteUrl||location.href).origin)return '';
      return url.origin+url.pathname.replace(/\/$/,'')+url.search;
    }catch(e){return '';}
  }
  function node(tag,cls,text){var n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;}
  function renderAnswer(box,text,sources){
    var cited=[];
    text=text.replace(/\[([^\]\n]+)\]\(\[page:(\d+)\]\)/gi,'[$1] [page:$2]');
    text=text.replace(/\*\*([^*\n]+)\*\*(?=\s*\[page:\d+\])/gi,'[$1]').replace(/__([^_\n]+)__(?=\s*\[page:\d+\])/gi,'[$1]');
    function sourceFor(match){var raw=match[3]||match[4]||'', address=match[4]?raw.replace(/[.,;:!?)]+$/,''):raw;var key=address&&pageKey(address);return match[1]?sources[Number(match[1])-1]:sources.find(function(item){return item&&key&&pageKey(item.url)===key;});}
    function inline(parent,line){
      var tokens=/\[page:(\d+)\]|\[([^\]\n]+)\]\(([^)\s]+)\)|(https?:\/\/[^\s<>"\]]+)|\*\*([^*\n]+)\*\*|__([^_\n]+)__/gi;
      var offset=0,m;
      while((m=tokens.exec(line))){
        var before=line.slice(offset,m.index),source=sourceFor(m);
        if(source&&source.url&&pageKey(source.url)){
          var title=String(source.title||m[2]||strings.openPage),trimmed=before.trimEnd(), variants=['['+title+']','**'+title+'**','__'+title+'__',title];
          variants.some(function(v){if(trimmed.toLocaleLowerCase().endsWith(v.toLocaleLowerCase())){var preceding=trimmed.slice(0,-v.length);if(v===title&&/[\p{L}\p{N}]$/u.test(preceding))return false;before=preceding;return true;}return false;});
          parent.appendChild(document.createTextNode(before));
          var link=node('a','w27public-inline-link',title);link.href=source.url;parent.appendChild(link);cited.push(pageKey(source.url));
        }else{parent.appendChild(document.createTextNode(before));if(m[5]||m[6])parent.appendChild(node('strong','',m[5]||m[6]));else parent.appendChild(document.createTextNode(m[2]||strings.page));}
        if(m[4])parent.appendChild(document.createTextNode(m[4].slice(m[4].replace(/[.,;:!?)]+$/,'').length)));
        offset=tokens.lastIndex;
      }
      parent.appendChild(document.createTextNode(line.slice(offset)));
    }
    var list=null;
    text.replace(/\r\n?/g,'\n').split('\n').forEach(function(line){
      if(!line.trim()){list=null;return;}
      var item=line.match(/^\s*(?:[-*•]\s+|(\d+)[.)]\s+)(.*)$/);
      if(item){var tag=item[1]?'ol':'ul';if(!list||list.tagName.toLowerCase()!==tag){list=node(tag,'w27public-answer-list');if(tag==='ol')list.start=Number(item[1]);box.appendChild(list);}var li=node('li');inline(li,item[2]);list.appendChild(li);return;}
      list=null;var heading=line.match(/^#{1,6}\s+(.+)$/),paragraph=node(heading?'h3':'p');inline(paragraph,heading?heading[1]:line);box.appendChild(paragraph);
    });return cited;
  }
  function imageKey(value){try{var u=new URL(value,document.baseURI);return pageKey(value)&&!u.search&&!u.hash&&!/\/(wp-admin|wp-json|wp-login\.php)(\/|$)/i.test(decodeURIComponent(u.pathname))&&/\.(png|jpe?g|webp|avif|gif)$/i.test(u.pathname);}catch(e){return false;}}
  function renderSources(root,items,cited){
    var box=root.querySelector('[data-w27public-sources]');box.replaceChildren();box.hidden=true;
    var seen=new Set(),list=[];
    (Array.isArray(items)?items:[]).forEach(function(s){var key=s&&pageKey(s.url);if(key&&!seen.has(key)){seen.add(key);list.push(s);}});
    list.sort(function(a,b){var i=cited.indexOf(pageKey(a.url)),j=cited.indexOf(pageKey(b.url));return (i<0?1000:i)-(j<0?1000:j);});
    if(!list.length)return;
    var head=node('div','w27public-source-head'),title=node('h3','',strings.related),grid=node('div','w27public-source-list');head.appendChild(title);
    if(root.dataset.w27Cards==='carousel'){
      var nav=node('div','w27public-carousel-controls');[[strings.previous||'Previous',-1],[strings.next||'Next',1]].forEach(function(a){var b=node('button','',a[1]<0?'‹':'›');b.type='button';b.setAttribute('aria-label',a[0]);b.onclick=function(){grid.scrollBy({left:a[1]*Math.max(180,grid.clientWidth*.75),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});};nav.appendChild(b);});head.appendChild(nav);
    }
    list.forEach(function(s){var a=node('a','w27public-source');a.href=s.url;var visual=node('span','w27public-source-visual','▤');visual.setAttribute('aria-hidden','true');if(s.image&&imageKey(s.image)){var img=node('img');img.alt='';img.loading='lazy';img.decoding='async';img.referrerPolicy='no-referrer';img.addEventListener('error',function(){visual.replaceChildren(document.createTextNode('▤'));});img.src=s.image;visual.replaceChildren(img);}var body=node('span','w27public-source-body');body.appendChild(node('span','w27public-source-title',s.title||strings.openPage));var excerpt=String(s.excerpt||'').trim();if(s.title&&excerpt.startsWith(s.title))excerpt=excerpt.slice(s.title.length).trim();if(excerpt)body.appendChild(node('span','w27public-source-text',excerpt.length>180?excerpt.slice(0,177)+'…':excerpt));body.appendChild(node('span','w27public-source-link',strings.openArrow));a.append(visual,body);grid.appendChild(a);});
    box.append(head,grid);box.hidden=false;
  }
  function roots(){return Array.prototype.slice.call(document.querySelectorAll('[data-w27public-root]'));}
  function setBusy(root,busy){root.dataset.w27publicBusy=String(busy);var answer=root.querySelector('[data-w27public-answer]');if(answer)answer.setAttribute('aria-busy',String(busy));var f=root.querySelector('[data-w27public-form]');var b=f&&f.querySelector('button[type="submit"]');if(b){b.disabled=busy;b.textContent=busy?strings.searching:strings.submit;}}
  function renderActions(root,items){
    var box=root.querySelector('[data-w27public-actions]'); if(!box)return;
    box.innerHTML=''; box.hidden=true;
    if(!Array.isArray(items)||!items.length)return;
    var wrap=document.createElement('div');wrap.className='w27public-action-list';
    items.slice(0,4).forEach(function(a){
      if(!a||!a.label)return;
      if(a.type==='query'&&a.query){
        var b=document.createElement('button');b.type='button';b.className='w27public-action';b.textContent=a.label;
        b.addEventListener('click',function(){var input=root.querySelector('[data-w27public-input]');if(input)input.value=a.query;ask(root,a.query);});wrap.appendChild(b);
      }else if(a.type==='link'&&a.url&&pageKey(a.url)){
        var link=document.createElement('a');link.className='w27public-action'+(a.variant==='primary'?' is-primary':'');link.href=a.url;link.textContent=a.label;
        wrap.appendChild(link);
      }
    });
    if(wrap.childNodes.length){box.appendChild(wrap);box.hidden=false;}
  }
  async function ask(root,q){
    q=(q||'').trim(); var input=root.querySelector('[data-w27public-input]');
    if(q.length<Number(input.minLength)||q.length>500){input.reportValidity();return;}
    if(root.dataset.w27publicBusy==='true')return;
    var state=root.querySelector('[data-w27public-state]'), answer=root.querySelector('[data-w27public-answer]'), sources=root.querySelector('[data-w27public-sources]'), actions=root.querySelector('[data-w27public-actions]');
    state.textContent=strings.searchingMaterials; if(actions)actions.hidden=true;setBusy(root,true);
    try{
      var r=await fetch(Web27SiteSearch.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({query:q,previous:root.dataset.topic||"",new_dialog:root.getAttribute('data-w27public-new-dialog')==='1',instance:root.dataset.instance||'primary',locale:Web27SiteSearch.locale}),credentials:'same-origin'});
      var data=await r.json();
      if(!r.ok)throw {publicMessage:data&&data.message?data.message:Web27SiteSearch.error};
      root.removeAttribute('data-w27public-new-dialog');
      var suggest=root.querySelector('.w27public-suggest');if(suggest)suggest.hidden=true;
      state.textContent=data.mode==='local_fallback'?strings.fallback:(['local','empty'].includes(data.mode)?strings.local:strings.received);
      answer.innerHTML='<div class="w27public-answer-name">'+esc(data.operator_name||Web27SiteSearch.operator)+'</div><div class="w27public-answer-text"></div>';var cited=renderAnswer(answer.querySelector('.w27public-answer-text'),String(data.answer||''),Array.isArray(data.sources)?data.sources:[]);answer.hidden=false;
      root.dataset.topic=data.topic||q;
      var candidates=Array.isArray(data.sources)?data.sources:[],confirmed=new Set(cited);
      var visible=data.mode==='external_ai'?candidates.filter(function(s){return s&&confirmed.has(pageKey(s.url));}):candidates;
      var sourceKeys=new Set(visible.map(function(s){return s&&pageKey(s.url);}));
      var buttons=(Array.isArray(data.actions)?data.actions:[]).filter(function(a){return a&&(a.type!=='link'||(data.mode!=='external_ai'&&!sourceKeys.has(pageKey(a.url))));});
      renderActions(root,buttons);
      renderSources(root,visible,cited);
      if(data.vote_token && actions){
        actions.hidden=false;
        for(const [label,vote] of [[strings.helpful,1],[strings.notHelpful,-1]]){
          const button=document.createElement('button'); button.type='button'; button.textContent=label;button.dataset.w27publicVote='1';
          button.onclick=async function(){
            try {
              const reply=await fetch(Web27SiteSearch.voteEndpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:data.vote_token,vote:vote,locale:Web27SiteSearch.locale})});
              if(reply.ok){state.textContent=strings.ratingSaved;actions.querySelectorAll('[data-w27public-vote]').forEach(function(b){b.disabled=true;});}else{state.textContent=strings.ratingError;}
            } catch(e){state.textContent=strings.ratingError;}
          };
          actions.appendChild(button);
        }
      }
    }catch(e){state.textContent='';sources.hidden=true;answer.innerHTML='<div class="w27public-error">'+esc(e.publicMessage||Web27SiteSearch.error)+'</div>';answer.hidden=false;}
    finally{setBusy(root,false);var results=root.querySelector('[data-w27public-results]');if(results)results.scrollTop=0;var layout=root.querySelector('.w27public-layout');if(layout)layout.scrollTop=0;}
  }
  function wire(root){
    if(!root||root.getAttribute('data-w27public-wired')==='1')return;root.setAttribute('data-w27public-wired','1');
    var form=root.querySelector('[data-w27public-form]'),input=root.querySelector('[data-w27public-input]');
    if(form)form.addEventListener('submit',function(e){e.preventDefault();ask(root,input.value);});
    root.querySelectorAll('[data-w27public-suggest]').forEach(function(b){b.addEventListener('click',function(){input.value=b.getAttribute('data-w27public-suggest')||'';ask(root,input.value);});});
    var reset=root.querySelector('[data-w27public-reset]');
    if(reset)reset.addEventListener('click',function(){if(root.dataset.w27publicBusy==='true')return;delete root.dataset.topic;root.setAttribute('data-w27public-new-dialog','1');input.value='';var suggest=root.querySelector('.w27public-suggest');if(suggest)suggest.hidden=false;root.querySelector('[data-w27public-state]').textContent='';['answer','actions','sources'].forEach(function(key){var box=root.querySelector('[data-w27public-'+key+']');if(box){box.hidden=true;box.innerHTML='';}});input.focus();});
    var c=root.querySelector('[data-w27public-close]');if(c)c.addEventListener('click',close);
  }
  var dialog=document.querySelector('[data-w27public-dialog]');
  var opener=null;
  var allRoots=roots();
  allRoots.forEach(function(root,index){
    root.dataset.instance=index===0?'primary':'panel-'+index;
    wire(root);
  });
  function fit(){
    if(!dialog||!dialog.open)return;
    var v=window.visualViewport;
    var width=v?v.width:window.innerWidth, height=v?v.height:window.innerHeight;
    dialog.style.setProperty('--w27public-vwidth',width+'px');
    dialog.style.setProperty('--w27public-vheight',height+'px');
    dialog.style.setProperty('--w27public-vtop',(v?v.offsetTop:0)+'px');
    dialog.style.setProperty('--w27public-vleft',(v?v.offsetLeft:0)+'px');
    dialog.classList.toggle('w27public-short',height<420);
  }
  function open(event){
    if(!dialog||dialog.open)return;
    opener=event&&event.currentTarget||document.activeElement;
    dialog.showModal();
    document.documentElement.classList.add('w27public-open');
    fit();
    var input=dialog.querySelector('[data-w27public-input]');
    if(input)input.focus({preventScroll:true});
  }
  function close(){if(dialog&&dialog.open)dialog.close();}
  if(dialog){
    dialog.addEventListener('keydown',function(event){
      if(event.key==='Escape'&&!event.isComposing){event.preventDefault();close();}
    });
    dialog.addEventListener('close',function(){
      document.documentElement.classList.remove('w27public-open');
      if(opener&&opener.isConnected)opener.focus({preventScroll:true});
      opener=null;
    });
    dialog.addEventListener('click',function(event){
      if(event.target!==dialog)return;
      var r=dialog.getBoundingClientRect();
      if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)close();
    });
    window.addEventListener('resize',fit);
    if(window.visualViewport){window.visualViewport.addEventListener('resize',fit);window.visualViewport.addEventListener('scroll',fit);}
  }
  document.querySelectorAll('[data-w27public-open]').forEach(function(b){b.addEventListener('click',open);});
})();
