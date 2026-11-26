(function(){
  'use strict';
  var preview=document.querySelector('[data-w27public-preview]'),launcher=document.querySelector('[data-w27public-preview-launcher]'),stage=document.querySelector('[data-w27-preview-stage]'),select=document.querySelector('[data-w27public-theme-select]');
  if(!select||!preview||!launcher||!stage)return;
  var form=select.closest('form'),mobile=form.querySelector('[data-w27-preview-mobile]');
  function contrast(hex){var rgb=hex.match(/[a-f\d]{2}/gi).map(function(v){var c=parseInt(v,16)/255;return c<=.04045?c/12.92:Math.pow((c+.055)/1.055,2.4);});return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2]>.179?'#000':'#fff';}
  function update(){
    var get=function(k){return form.elements['design['+k+']'].value;};
    [preview,launcher,stage].forEach(function(n){n.dataset.w27publicTheme=select.value;['placement','mobile','shape','content','window','cards'].forEach(function(k){n.dataset['w27'+k[0].toUpperCase()+k.slice(1)]=get(k);});['width','height','offset_x','offset_y','radius'].forEach(function(k){var el=form.elements['design['+k+']'],v=Math.max(Number(el.min),Math.min(Number(el.max),Number(el.value)||Number(el.min)));n.style.setProperty('--w27-'+k.replace('_','-'),v+'px');});['accent','background','surface','text'].forEach(function(k){n.style.setProperty('--w27-custom-'+k,get(k));});n.style.setProperty('--w27-custom-button-text',contrast(get('accent')));n.dataset.mobile=mobile.checked?'1':'0';});
    if(mobile.checked&&get('mobile')!=='inherit')launcher.dataset.w27Placement=get('mobile');
    launcher.hidden=get('visibility')==='never';
    var labels={'panel_title':'.w27public-head h2','operator_name':'.w27public-answer-name'};Object.keys(labels).forEach(function(k){var input=form.elements['settings['+k+']'];if(input)preview.querySelector(labels[k]).textContent=input.value;});launcher.querySelector('.w27public-launcher-label').textContent=form.elements['settings[launcher_label]'].value;
  }
  form.addEventListener('input',update);form.addEventListener('change',update);update();
})();
