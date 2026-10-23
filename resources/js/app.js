import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js';
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

const passwordStrengthInput = document.querySelector('[data-password-strength-input]');
const passwordStrengthMeter = document.querySelector('[data-password-strength-meter]');
const passwordStrengthBar = document.querySelector('[data-password-strength-bar]');
const passwordStrengthLabel = document.querySelector('[data-password-strength-label]');

if (passwordStrengthInput instanceof HTMLInputElement && passwordStrengthMeter && passwordStrengthBar && passwordStrengthLabel) {
  const strengthLabels = ['Enter a password', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];

  passwordStrengthInput.addEventListener('input', () => {
    const password = passwordStrengthInput.value;
    const checks = [
      password.length >= 12,
      password.length >= 16,
      /[a-z]/.test(password) && /[A-Z]/.test(password),
      /\d/.test(password),
      /[^A-Za-z0-9]/.test(password),
    ];
    const score = password.length === 0 ? 0 : checks.filter(Boolean).length;
    const label = strengthLabels[score];

    passwordStrengthMeter.setAttribute('aria-valuenow', String(score));
    passwordStrengthMeter.setAttribute('aria-valuetext', label);
    passwordStrengthMeter.dataset.strength = String(score);
    passwordStrengthBar.style.width = `${score * 20}%`;
    passwordStrengthLabel.textContent = label;
  });
}
const menu = document.querySelector('[data-menu-toggle]');
const header = menu?.closest('.console-header');
const menuIcon = menu?.querySelector('i');
const mainNavigation = document.getElementById('main-navigation');

const closeMenu = () => {
  if (!menu || !header || !menuIcon) {
    return;
  }

  header.classList.remove('menu-open');
  document.body.classList.remove('public-menu-open');
  document.querySelectorAll('#main-content, .site-footer').forEach((region) => {
    region.inert = false;
  });
  menu.setAttribute('aria-expanded', 'false');
  menu.setAttribute('aria-label', 'Open menu');
  menuIcon.classList.remove('fa-xmark');
  menuIcon.classList.add('fa-bars');
};

menu?.addEventListener('click', () => {
  if (!header || !menuIcon) {
    return;
  }

  const open = header.classList.toggle('menu-open');
  document.body.classList.toggle('public-menu-open', open);
  document.querySelectorAll('#main-content, .site-footer').forEach((region) => {
    region.inert = open;
  });
  menu.setAttribute('aria-expanded', String(open));
  menu.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  menuIcon.classList.toggle('fa-bars', !open);
  menuIcon.classList.toggle('fa-xmark', open);

  if (open) {
    requestAnimationFrame(() => mainNavigation?.querySelector('a')?.focus({ preventScroll: true }));
  }
});

mainNavigation?.querySelectorAll('a').forEach((link) => {
  link.addEventListener('click', closeMenu);
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && menu?.getAttribute('aria-expanded') === 'true') {
    closeMenu();
    menu.focus();
  }
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 760) {
    closeMenu();
  }
});

const parallaxHero = document.querySelector('[data-scroll-parallax]');
const parallaxLayers = parallaxHero ? [...parallaxHero.querySelectorAll('[data-parallax-speed]')] : [];
const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

const testimonialCarousel = document.querySelector('[data-testimonial-carousel]');
if (testimonialCarousel) {
  const testimonialSlides = [...testimonialCarousel.querySelectorAll('[data-testimonial-slide]')];
  const testimonialSelectors = [...testimonialCarousel.querySelectorAll('[data-testimonial-select]')];
  const previousTestimonial = testimonialCarousel.querySelector('[data-testimonial-prev]');
  const nextTestimonial = testimonialCarousel.querySelector('[data-testimonial-next]');
  let activeTestimonialIndex = 0;

  const fitQuoteText = (slide) => {
    const quote = slide?.querySelector('blockquote');
    const text = quote?.querySelector('p');
    if (!quote || !text || quote.clientHeight === 0) return;

    text.style.removeProperty('--quote-font-size');
    const baseSize = Number.parseFloat(window.getComputedStyle(text).fontSize);
    const minimumSize = window.innerWidth <= 760 ? 17 : 19;
    let size = baseSize;
    text.style.setProperty('--quote-font-size', `${size}px`);

    while (quote.scrollHeight > quote.clientHeight && size > minimumSize) {
      size = Math.max(minimumSize, size - 1);
      text.style.setProperty('--quote-font-size', `${size}px`);
    }
  };

  const fitActiveQuoteText = () => fitQuoteText(testimonialSlides[activeTestimonialIndex]);

  const showTestimonial = (requestedIndex) => {
    activeTestimonialIndex = (requestedIndex + testimonialSlides.length) % testimonialSlides.length;

    testimonialSlides.forEach((slide, index) => {
      const isActive = index === activeTestimonialIndex;
      slide.hidden = !isActive;
      slide.setAttribute('aria-hidden', String(!isActive));
      slide.classList.toggle('is-active', isActive);
      slide.classList.remove('is-entering');

      if (isActive) {
        requestAnimationFrame(() => {
          fitQuoteText(slide);
          if (!reducedMotionQuery.matches) slide.classList.add('is-entering');
        });
      }
    });

    testimonialSelectors.forEach((selector, index) => {
      selector.setAttribute('aria-pressed', String(index === activeTestimonialIndex));
    });
  };

  testimonialCarousel.classList.add('is-enhanced');
  window.addEventListener('resize', fitActiveQuoteText);
  testimonialSelectors.forEach((selector) => {
    selector.addEventListener('click', () => {
      showTestimonial(Number(selector.dataset.testimonialSelect));
    });
  });
  previousTestimonial?.addEventListener('click', () => showTestimonial(activeTestimonialIndex - 1));
  nextTestimonial?.addEventListener('click', () => showTestimonial(activeTestimonialIndex + 1));
  testimonialCarousel.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      showTestimonial(activeTestimonialIndex - 1);
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      showTestimonial(activeTestimonialIndex + 1);
    } else if (event.key === 'Home') {
      event.preventDefault();
      showTestimonial(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      showTestimonial(testimonialSlides.length - 1);
    }
  });

  showTestimonial(activeTestimonialIndex);
}
let parallaxFrameQueued = false;

const updateParallax = () => {
  parallaxFrameQueued = false;

  if (!parallaxHero || reducedMotionQuery.matches) {
    return;
  }

  const heroBounds = parallaxHero.getBoundingClientRect();
  const scrollProgress = Math.min(Math.max(-heroBounds.top, 0), heroBounds.height);
  const screenFactor = window.innerWidth <= 760 ? 0.5 : 1;

  parallaxLayers.forEach((layer) => {
    const speed = Number(layer.dataset.parallaxSpeed || 0);
    layer.style.setProperty('--parallax-offset', String(scrollProgress * speed * screenFactor) + 'px');
  });
};

const requestParallaxUpdate = () => {
  if (parallaxFrameQueued || reducedMotionQuery.matches) {
    return;
  }

  parallaxFrameQueued = true;
  window.requestAnimationFrame(updateParallax);
};

if (parallaxHero) {
  window.addEventListener('scroll', requestParallaxUpdate, {passive: true});
  window.addEventListener('resize', requestParallaxUpdate);
  requestParallaxUpdate();

  reducedMotionQuery.addEventListener('change', (event) => {
    if (event.matches) {
      parallaxLayers.forEach((layer) => layer.style.removeProperty('--parallax-offset'));
    } else {
      requestParallaxUpdate();
    }
  });
}
const usageCanvas = document.getElementById('usage-chart');
const usageData = document.getElementById('usage-chart-data');
if (usageCanvas && usageData) {
  const {labels, values} = JSON.parse(usageData.textContent);
  new Chart(usageCanvas, {type:'line',data:{labels,datasets:[{label:'kWh per interval',data:values,borderColor:'#0B8F7A',backgroundColor:'rgba(11,143,122,.1)',fill:true,tension:.3,pointRadius:0,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},plugins:{legend:{display:false}},scales:{x:{ticks:{maxTicksLimit:7,color:'#53686D'},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#53686D'},grid:{color:'#e8efea'}}}}});
}

const billCanvas = document.getElementById('bill-chart');
const billData = document.getElementById('bill-chart-data');
if (billCanvas && billData) {
  const {labels, amounts} = JSON.parse(billData.textContent);
  new Chart(billCanvas, {type:'line',data:{labels,datasets:[{label:'Monthly bill',data:amounts,borderColor:'#0B8F7A',backgroundColor:'rgba(11,143,122,.1)',fill:true,tension:.25,pointRadius:4,pointBackgroundColor:'#0B8F7A',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#53686D'}},y:{beginAtZero:true,grid:{color:'#e8efea'},ticks:{color:'#53686D'}}}}});
}

// Progressively enhance the public story; all content remains readable without JavaScript.
const storyPage = document.querySelector('[data-story-page]');
if (storyPage) {
  const scenes = [...storyPage.querySelectorAll('[data-scroll-scene]')].map((element) => ({
    element,
    image: element.querySelector('[data-scene-image]'),
    steps: [...element.querySelectorAll('[data-story-step]')],
    caption: element.querySelector('[data-scene-caption]'),
  }));
  const sectionLinks = [...document.querySelectorAll('[data-section-link]')];
  const sections = sectionLinks.map((link) => ({link, section: document.getElementById(link.dataset.sectionLink)}));
  let frame = 0;
  const renderStory = () => {
    frame = 0;
    const viewport = window.innerHeight;
    const headerHeight = header?.offsetHeight || 96;
    const availableScroll = document.documentElement.scrollHeight - viewport;
    header?.style.setProperty('--reading-progress', String(availableScroll > 0 ? window.scrollY / availableScroll : 0));
    for (const scene of scenes) {
      const bounds = scene.element.getBoundingClientRect();
      if (bounds.bottom < 0 || bounds.top > viewport) continue;
      const progress = Math.max(0, Math.min(1, (viewport - bounds.top) / (bounds.height + viewport)));
      if (scene.image && !reducedMotionQuery.matches) {
        const amplitude = window.innerWidth <= 760 ? 10 : 24;
        scene.image.style.setProperty('--scene-shift', `${(0.5 - progress) * amplitude}px`);
        scene.image.style.setProperty('--scene-scale', String(1.07 + progress * 0.12));
      }
      if (scene.steps.length) {
        let active = scene.steps[0];
        for (const step of scene.steps) {
          if (step.getBoundingClientRect().top < headerHeight + (viewport - headerHeight) * 0.5) active = step;
        }
        scene.steps.forEach((step) => step.classList.toggle('is-current', step === active));
        if (scene.caption && scene.caption.textContent !== active.dataset.caption) scene.caption.textContent = active.dataset.caption;
      }
    }
    for (const {link, section} of sections) {
      if (!section) continue;
      const bounds = section.getBoundingClientRect();
      if (bounds.top <= headerHeight + 70 && bounds.bottom > headerHeight + 70) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    }
  };
  const queueStory = () => { if (!frame) frame = requestAnimationFrame(renderStory); };
  window.addEventListener('scroll', queueStory, {passive: true});
  window.addEventListener('resize', queueStory);
  const revealElements = [...storyPage.querySelectorAll('[data-reveal]')];
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.remove('reveal-pending');
          observer.unobserve(entry.target);
        }
      }
    }, {threshold: 0, rootMargin: '0px 0px -30px 0px'});
    for (const element of revealElements) {
      if (!reducedMotionQuery.matches && element.getBoundingClientRect().top > window.innerHeight) {
        element.classList.add('reveal-pending');
        observer.observe(element);
      }
    }
  }
  storyPage.addEventListener('focusin', (event) => event.target.closest('[data-reveal]')?.classList.remove('reveal-pending'));
  reducedMotionQuery.addEventListener('change', () => {
    if (reducedMotionQuery.matches) {
      revealElements.forEach((element) => element.classList.remove('reveal-pending'));
      scenes.forEach(({image}) => {
        image?.style.removeProperty('--scene-shift');
        image?.style.removeProperty('--scene-scale');
      });
    }
    queueStory();
  });
  const openHashTarget = () => {
    let id;
    try { id = decodeURIComponent(window.location.hash.slice(1)); } catch { return; }
    const target = document.getElementById(id);
    if (!target) return;
    let ancestor = target;
    while (ancestor) {
      if (ancestor instanceof HTMLDetailsElement) ancestor.open = true;
      ancestor.classList?.remove('reveal-pending');
      ancestor = ancestor.parentElement;
    }
    requestAnimationFrame(() => {
      target.scrollIntoView({block: 'start', behavior: 'instant'});
      queueStory();
    });
  };
  window.addEventListener('hashchange', openHashTarget);
  storyPage.querySelectorAll('details').forEach((details) => details.addEventListener('toggle', queueStory));
  openHashTarget();
  queueStory();
}


const campusSalesDialog = document.querySelector('[data-sales-dialog]');
if (campusSalesDialog instanceof HTMLDialogElement) {
  const salesDialogOpeners = [...document.querySelectorAll('[data-sales-dialog-open]')];
  const salesDialogClosers = [...campusSalesDialog.querySelectorAll('[data-sales-dialog-close]')];

  salesDialogOpeners.forEach((opener) => {
    opener.addEventListener('click', () => campusSalesDialog.showModal());
  });
  salesDialogClosers.forEach((closer) => {
    closer.addEventListener('click', () => campusSalesDialog.close());
  });
  campusSalesDialog.addEventListener('click', (event) => {
    if (event.target === campusSalesDialog) campusSalesDialog.close();
  });

  if (campusSalesDialog.dataset.openOnLoad === 'true') campusSalesDialog.showModal();
}


const footerRevealElements = [...document.querySelectorAll('footer.site-footer [data-reveal]')];
if (footerRevealElements.length && 'IntersectionObserver' in window) {
  let footerRevealObserver;
  const clearFooterReveal = () => {
    footerRevealElements.forEach((element) => element.classList.remove('reveal-pending'));
    footerRevealObserver?.disconnect();
  };

  if (!reducedMotionQuery.matches) {
    footerRevealObserver = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.remove('reveal-pending');
          footerRevealObserver.unobserve(entry.target);
        }
      }
    }, {threshold: 0, rootMargin: '0px 0px -30px 0px'});

    for (const element of footerRevealElements) {
      if (element.getBoundingClientRect().top > window.innerHeight) {
        element.classList.add('reveal-pending');
        footerRevealObserver.observe(element);
      }
    }

    document.addEventListener('focusin', (event) => {
      const reveal = event.target.closest('footer.site-footer [data-reveal]');
      reveal?.classList.remove('reveal-pending');
    });
  }

  reducedMotionQuery.addEventListener('change', (event) => {
    if (event.matches) clearFooterReveal();
  });
}
