(function(){
    'use strict';
    function init(){
        var sidebar=document.querySelector('.sidebar');
        var overlay=document.querySelector('.sidebar-overlay');
        var hamburger=document.querySelector('.hamburger-btn');
        if(!sidebar) return;
        function open(){sidebar.classList.add('open');if(overlay)overlay.classList.add('active');if(hamburger)hamburger.classList.add('is-open');document.body.style.overflow='hidden';}
        function close(){sidebar.classList.remove('open');if(overlay)overlay.classList.remove('active');if(hamburger)hamburger.classList.remove('is-open');document.body.style.overflow='';}
        if(hamburger)hamburger.addEventListener('click',function(){sidebar.classList.contains('open')?close():open();});
        if(overlay)overlay.addEventListener('click',close);
        document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
        sidebar.querySelectorAll('.nav-item[href]').forEach(function(item){item.addEventListener('click',function(){if(window.innerWidth<768)close();});});
        window.addEventListener('resize',function(){if(window.innerWidth>=768)close();});
    }
    document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();
