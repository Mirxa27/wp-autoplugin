/**
 * Animation utilities
 */

/**
 * Animate an element with a predefined animation
 */
export function animateElement(element, animation, duration = 300) {
    return new Promise((resolve) => {
        const animations = {
            fadeIn: [
                { opacity: 0 },
                { opacity: 1 }
            ],
            fadeOut: [
                { opacity: 1 },
                { opacity: 0 }
            ],
            slideIn: [
                { 
                    opacity: 0,
                    transform: 'translateY(20px)'
                },
                { 
                    opacity: 1,
                    transform: 'translateY(0)'
                }
            ],
            slideOut: [
                { 
                    opacity: 1,
                    transform: 'translateY(0)'
                },
                { 
                    opacity: 0,
                    transform: 'translateY(20px)'
                }
            ],
            scaleIn: [
                {
                    opacity: 0,
                    transform: 'scale(0.9)'
                },
                {
                    opacity: 1,
                    transform: 'scale(1)'
                }
            ],
            scaleOut: [
                {
                    opacity: 1,
                    transform: 'scale(1)'
                },
                {
                    opacity: 0,
                    transform: 'scale(0.9)'
                }
            ],
            shake: [
                { transform: 'translateX(0)' },
                { transform: 'translateX(-10px)' },
                { transform: 'translateX(10px)' },
                { transform: 'translateX(-10px)' },
                { transform: 'translateX(10px)' },
                { transform: 'translateX(0)' }
            ]
        };

        if (!animations[animation]) {
            resolve();
            return;
        }

        const anim = element.animate(animations[animation], {
            duration,
            easing: 'ease-in-out',
            fill: 'forwards'
        });

        anim.onfinish = resolve;
    });
}

/**
 * Fade in an element
 */
export function fadeIn(element, duration = 300) {
    return animateElement(element, 'fadeIn', duration);
}

/**
 * Fade out an element
 */
export function fadeOut(element, duration = 300) {
    return animateElement(element, 'fadeOut', duration);
}

/**
 * Slide in an element
 */
export function slideIn(element, duration = 300) {
    return animateElement(element, 'slideIn', duration);
}

/**
 * Slide out an element
 */
export function slideOut(element, duration = 300) {
    return animateElement(element, 'slideOut', duration);
}

/**
 * Create a stagger animation effect
 */
export function staggerAnimation(elements, animation, duration = 300, delay = 50) {
    const promises = Array.from(elements).map((element, index) => {
        return new Promise((resolve) => {
            setTimeout(() => {
                animateElement(element, animation, duration).then(resolve);
            }, index * delay);
        });
    });

    return Promise.all(promises);
}

/**
 * Animate a counter
 */
export function animateCounter(element, start, end, duration = 1000) {
    const startTime = performance.now();
    const updateCounter = (currentTime) => {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);
        
        const currentValue = Math.floor(progress * (end - start) + start);
        element.textContent = currentValue;
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = end;
        }
    };
    
    requestAnimationFrame(updateCounter);
}

/**
 * Create a typing effect
 */
export function typeWriter(element, text, speed = 50) {
    return new Promise((resolve) => {
        let i = 0;
        element.textContent = '';
        
        const type = () => {
            if (i < text.length) {
                element.textContent += text.charAt(i);
                i++;
                setTimeout(type, speed);
            } else {
                resolve();
            }
        };
        
        type();
    });
}

/**
 * Add ripple effect to an element
 */
export function addRippleEffect(element) {
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    
    element.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            pointer-events: none;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
        `;
        
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    });
    
    // Add ripple animation if not already present
    if (!document.querySelector('#wp-autoplugin-ripple-keyframes')) {
        const style = document.createElement('style');
        style.id = 'wp-autoplugin-ripple-keyframes';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Create skeleton loading effect
 */
export function createSkeleton(container, lines = 3) {
    const skeleton = document.createElement('div');
    skeleton.className = 'wp-autoplugin-skeleton';
    skeleton.style.cssText = `
        padding: 16px;
    `;
    
    for (let i = 0; i < lines; i++) {
        const line = document.createElement('div');
        line.className = 'skeleton-line';
        line.style.cssText = `
            height: 16px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            margin-bottom: 8px;
            border-radius: 4px;
            width: ${i === lines - 1 ? '60%' : '100%'};
        `;
        skeleton.appendChild(line);
    }
    
    container.appendChild(skeleton);
    
    // Add loading animation if not already present
    if (!document.querySelector('#wp-autoplugin-loading-keyframes')) {
        const style = document.createElement('style');
        style.id = 'wp-autoplugin-loading-keyframes';
        style.textContent = `
            @keyframes loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    return skeleton;
}