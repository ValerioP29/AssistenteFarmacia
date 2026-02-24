
document.addEventListener('DOMContentLoaded', () => {

  hamburgerlist = document.getElementById('hamburger-list');
  sidebarcontainer = document.getElementById('sidebarcontainer');
  active = false;

  if (hamburgerlist) {
    hamburgerlist.addEventListener('click', (e) => {
      e.preventDefault();
    
      if(!active) {
      sidebarcontainer.classList.remove('left-area');
      
      active = true;
      console.log(active);
      }else {
      sidebarcontainer.classList.add('left-area');
      active = false;
      console.log(active);
      }
    });
  }

});