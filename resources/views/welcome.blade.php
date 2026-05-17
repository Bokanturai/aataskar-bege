<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A A TASKAR BEGE | Nigeria's Leading Digital Agency for NIN, BVN & CAC</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="A A TASKAR BEGE provides professional and secure agency services including NIN verification, BVN enrollment, JAMB pins, and CAC business registrations in Nigeria.">
    <meta name="keywords" content="NIN Nigeria, BVN enrollment, JAMB pin, CAC registration, A A TASKAR BEGE, Zuru agency, digital services Nigeria">
    <meta name="author" content="A A TASKAR BEGE">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="A A TASKAR BEGE | Premium Agency Services & Solutions">
    <meta property="og:description" content="Streamlined, secure, and professional solutions for NIN, BVN, JAMB, and CAC registrations.">
    <meta property="og:image" content="{{ asset('assets/images/logo.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url('/') }}">
    <meta property="twitter:title" content="A A TASKAR BEGE | Premium Agency Services & Solutions">
    <meta property="twitter:description" content="Streamlined, secure, and professional solutions for NIN, BVN, JAMB, and CAC registrations.">
    <meta property="twitter:image" content="{{ asset('assets/images/logo.png') }}">

    <!-- Scripts & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="{{ asset('assets/images/logo.png') }}" type="image/x-icon">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f1f8f2',
                            100: '#ddeee0',
                            200: '#bdddc4',
                            300: '#91c39e',
                            400: '#64a375',
                            500: '#365839', // Base Brand Color
                            600: '#327a4a',
                            700: '#2a623d',
                            800: '#244e33',
                            900: '#1f412c',
                            950: '#102419',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .text-gradient {
            background: linear-gradient(135deg, #365839 0%, #64a375 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary {
            @apply bg-primary-500 text-white px-8 py-3 rounded-xl font-semibold transition-all duration-300 hover:bg-primary-600 hover:shadow-lg hover:shadow-primary-500/30 active:scale-95;
        }

        .btn-secondary {
            @apply bg-white text-primary-500 border border-primary-100 px-8 py-3 rounded-xl font-semibold transition-all duration-300 hover:bg-primary-50 hover:border-primary-200 active:scale-95;
        }

        #loader-wrapper {
            position: fixed;
            inset: 0;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
            transition: opacity 0.5s ease-out;
        }

        .loader-dots {
            display: flex;
            gap: 8px;
        }

        .dot {
            width: 12px;
            height: 12px;
            background: #365839;
            border-radius: 50%;
            animation: bounce 0.6s infinite alternate;
        }

        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes bounce {
            to { transform: translateY(-10px); opacity: 0.5; }
        }

        .service-card {
            @apply bg-white p-8 rounded-3xl border border-slate-100 transition-all duration-500 hover:border-primary-200 hover:shadow-2xl hover:shadow-primary-500/10 hover:-translate-y-2;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 selection:bg-primary-100 selection:text-primary-900">

    <!-- Loader -->
    <div id="loader-wrapper">
        <div class="loader-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 glass mt-4 rounded-2xl px-6">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" class="h-10 w-auto rounded-lg" />
                    <span class="text-xl font-heading font-bold text-slate-800 tracking-tight">A A TASKAR BEGE</span>
                </div>
                
                <div class="hidden md:flex items-center gap-8">
                    <a href="#" class="text-sm font-medium text-primary-600">Home</a>
                    <a href="#services" class="text-sm font-medium text-slate-600 hover:text-primary-500 transition-colors">Services</a>
                    <a href="#about" class="text-sm font-medium text-slate-600 hover:text-primary-500 transition-colors">About</a>
                    <a href="#contact" class="text-sm font-medium text-slate-600 hover:text-primary-500 transition-colors">Contact</a>
                </div>

                <div class="flex items-center gap-4">
                    <a href="{{ route('auth.login') }}" class="hidden sm:block text-sm font-semibold text-slate-600 hover:text-primary-500 transition-colors">Log in</a>
                    <a href="{{ route('auth.register') }}" class="bg-primary-500 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-600 transition-all shadow-md shadow-primary-500/20">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10">
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary-100/50 rounded-full blur-[120px] animate-pulse"></div>
                <div class="absolute bottom-[10%] right-[-5%] w-[30%] h-[30%] bg-emerald-100/50 rounded-full blur-[100px]"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="animate-fade-in-up" style="animation-delay: 0.1s">
                        <div class="inline-flex items-center gap-2 bg-primary-50 text-primary-700 px-4 py-2 rounded-full text-sm font-semibold mb-6 border border-primary-100">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-primary-500"></span>
                            </span>
                            Trusted by 10,000+ Users
                        </div>
                        <h1 class="text-5xl lg:text-7xl font-heading font-bold text-slate-900 leading-[1.1] mb-6">
                            Elevate Your <span class="text-gradient">Agency Experience</span>
                        </h1>
                        <p class="text-lg text-slate-600 leading-relaxed mb-10 max-w-lg">
                            A A TASKAR BEGE provides professional-grade solutions for NIN, BVN, JAMB, and CAC registrations. Streamlined, secure, and built for your growth.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <button class="btn-primary">Explore Services</button>
                            <button class="btn-secondary">Learn More</button>
                        </div>
                        
                        <div class="mt-12 flex items-center gap-6 border-t border-slate-200 pt-8">
                            <div class="flex -space-x-3">
                                <img class="w-10 h-10 rounded-full border-2 border-white bg-slate-200" src="https://i.pravatar.cc/100?img=1" alt="User">
                                <img class="w-10 h-10 rounded-full border-2 border-white bg-slate-200" src="https://i.pravatar.cc/100?img=2" alt="User">
                                <img class="w-10 h-10 rounded-full border-2 border-white bg-slate-200" src="https://i.pravatar.cc/100?img=3" alt="User">
                                <div class="w-10 h-10 rounded-full border-2 border-white bg-primary-500 flex items-center justify-center text-[10px] text-white font-bold">+2k</div>
                            </div>
                            <p class="text-sm text-slate-500 font-medium">Join our growing community today</p>
                        </div>
                    </div>

                    <div class="relative animate-fade-in-up" style="animation-delay: 0.3s">
                        <div class="relative z-10 rounded-[2.5rem] overflow-hidden shadow-2xl animate-float">
                            <img src="{{ asset('assets/images/img/img03.jpg') }}" alt="Professional digital agency services in Nigeria - A A TASKAR BEGE" class="w-full h-auto object-cover" 
                                 onerror="this.src='https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&q=80&w=1000'">
                        </div>
                        <!-- Decorative Elements -->
                        <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-primary-500/10 rounded-full blur-3xl -z-10"></div>
                        <div class="absolute top-10 -left-10 glass p-6 rounded-3xl shadow-xl animate-float" style="animation-delay: 1s">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                                    <i class="fas fa-shield-alt text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">100% Secure</p>
                                    <p class="text-xs text-slate-500">Verified Process</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section id="services" class="py-24 bg-white relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-20">
                    <h2 class="text-primary-500 font-bold tracking-widest uppercase text-sm mb-4">Our Expertise</h2>
                    <h3 class="text-4xl lg:text-5xl font-heading font-bold text-slate-900 mb-6">Services Tailored for You</h3>
                    <p class="text-slate-500 text-lg">We simplify complex governmental and business processes through our dedicated service portals.</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- NIN -->
                    <div class="service-card">
                        <div class="w-16 h-16 rounded-2xl bg-primary-50 flex items-center justify-center mb-8 group-hover:bg-primary-500 transition-colors">
                            <img src="{{ asset('assets/images/img/nimc.png') }}" alt="NIN" class="w-10 h-10 object-contain" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1160/1160358.png'">
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 mb-4">NIN Services</h4>
                        <p class="text-slate-500 leading-relaxed mb-6">Professional NIN verification and management services with tracking integration.</p>
                        <a href="#" class="text-primary-500 font-bold inline-flex items-center gap-2 group">
                            Learn More <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>

                    <!-- BVN -->
                    <div class="service-card">
                        <div class="w-16 h-16 rounded-2xl bg-primary-50 flex items-center justify-center mb-8">
                            <img src="{{ asset('assets/images/img/bvn.png') }}" alt="BVN" class="w-10 h-10 object-contain" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2830/2830284.png'">
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 mb-4">BVN Verification</h4>
                        <p class="text-slate-500 leading-relaxed mb-6">Swift BVN enrollment access and slip downloads for businesses and individuals.</p>
                        <a href="#" class="text-primary-500 font-bold inline-flex items-center gap-2 group">
                            Learn More <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>

                    <!-- JAMB -->
                    <div class="service-card">
                        <div class="w-16 h-16 rounded-2xl bg-primary-50 flex items-center justify-center mb-8">
                            <img src="{{ asset('assets/images/img/jamb.png') }}" alt="JAMB" class="w-10 h-10 object-contain" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2940/2940651.png'">
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 mb-4">JAMB & DE Pins</h4>
                        <p class="text-slate-500 leading-relaxed mb-6">Empower your educational journey. Buy JAMB and DE pins with instant delivery.</p>
                        <a href="#" class="text-primary-500 font-bold inline-flex items-center gap-2 group">
                            Learn More <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>

                    <!-- CAC -->
                    <div class="service-card">
                        <div class="w-16 h-16 rounded-2xl bg-primary-50 flex items-center justify-center mb-8">
                            <img src="{{ asset('assets/images/img/cac.png') }}" alt="CAC" class="w-10 h-10 object-contain" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3061/3061341.png'">
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 mb-4">CAC Registration</h4>
                        <p class="text-slate-500 leading-relaxed mb-6">Register your business legally. We handle the paperwork for your CAC upgrades.</p>
                        <a href="#" class="text-primary-500 font-bold inline-flex items-center gap-2 group">
                            Learn More <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="py-24 bg-primary-50/30 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="relative order-2 lg:order-1 animate-fade-in-up">
                        <div class="relative z-10 rounded-[2.5rem] overflow-hidden shadow-2xl">
                            <img src="{{ asset('assets/images/img/img (8).jpg') }}" alt="About A A TASKAR BEGE" class="w-full h-[500px] object-cover"
                                 onerror="this.src='https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&q=80&w=1000'">
                        </div>
                        <!-- Stats Overlay -->
                        <div class="absolute -bottom-8 -right-8 glass p-8 rounded-3xl shadow-xl z-20 hidden md:block">
                            <div class="grid grid-cols-2 gap-8">
                                <div>
                                    <p class="text-3xl font-bold text-primary-500">5+</p>
                                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Years Experience</p>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold text-primary-500">10k+</p>
                                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Happy Clients</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="order-1 lg:order-2 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h2 class="text-primary-500 font-bold tracking-widest uppercase text-sm mb-4">Who We Are</h2>
                        <h3 class="text-4xl lg:text-5xl font-heading font-bold text-slate-900 mb-8">Your Trusted Partner in <span class="text-gradient">Digital Growth</span></h3>
                        <p class="text-slate-600 text-lg leading-relaxed mb-8">
                            A A TASKAR BEGE was founded on the principle of making essential digital services accessible to everyone. We bridge the gap between complex governmental requirements and the everyday user.
                        </p>
                        
                        <div class="space-y-6 mb-10">
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 flex-shrink-0 mt-1">
                                    <i class="fas fa-check text-[10px]"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900">Efficiency First</h4>
                                    <p class="text-sm text-slate-500">We prioritize speed without compromising on security or accuracy.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 flex-shrink-0 mt-1">
                                    <i class="fas fa-check text-[10px]"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900">Expert Guidance</h4>
                                    <p class="text-sm text-slate-500">Our team consists of specialists who understand the ins and outs of agency services.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 flex-shrink-0 mt-1">
                                    <i class="fas fa-check text-[10px]"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900">Secure Processes</h4>
                                    <p class="text-sm text-slate-500">Your data privacy and security are at the heart of everything we do.</p>
                                </div>
                            </div>
                        </div>

                        <a href="#contact" class="btn-primary inline-block">Get in Touch</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact" class="py-24 bg-slate-50 relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-2 gap-20">
                    <div>
                        <h2 class="text-primary-500 font-bold uppercase tracking-widest text-sm mb-4">Contact Us</h2>
                        <h3 class="text-4xl lg:text-5xl font-heading font-bold text-slate-900 mb-8">Ready to Start a <span class="text-gradient">Project?</span></h3>
                        <p class="text-slate-600 text-lg mb-12">Have questions about our services? Our team is here to provide you with expert guidance and support.</p>
                        
                        <div class="space-y-8">
                            <div class="flex items-start gap-6">
                                <div class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center text-primary-500 flex-shrink-0">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900">Headquarters</p>
                                    <p class="text-slate-500">Tudun wada street opposite primary school mafara</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-6">
                                <div class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center text-primary-500 flex-shrink-0">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900">Phone Support</p>
                                    <p class="text-slate-500">+234 8030564012</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-6">
                                <div class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center text-primary-500 flex-shrink-0">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900">Email Address</p>
                                    <p class="text-slate-500">abdulazizabubakartma2030@gmail.com</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl shadow-primary-500/5 border border-slate-100">
                        <form action="#" method="POST" class="space-y-6">
                            @csrf
                            <div class="grid sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Full Name</label>
                                    <input type="text" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 transition-all" placeholder="John Doe">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Email Address</label>
                                    <input type="email" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 transition-all" placeholder="john@example.com">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Subject</label>
                                <input type="text" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 transition-all" placeholder="How can we help?">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Message</label>
                                <textarea rows="4" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 transition-all" placeholder="Tell us more about your request..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary w-full py-4 text-lg">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 text-slate-400 pt-20 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
                <!-- Brand -->
                <div class="lg:col-span-1">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" class="h-12 w-auto" />
                        <span class="text-xl font-heading font-bold text-white">A A TASKAR BEGE</span>
                    </div>
                    <p class="mb-8 text-sm leading-relaxed">
                        Leading the way in digital agency services across Nigeria. We provide secure, efficient, and reliable solutions for business and individual needs.
                    </p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-primary-500 hover:text-white transition-all">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-primary-500 hover:text-white transition-all">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-primary-500 hover:text-white transition-all">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-primary-500 hover:text-white transition-all">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <!-- Services -->
                <div>
                    <h4 class="text-white font-bold mb-6">Services</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="#" class="hover:text-primary-400 transition-colors">NIN Verification</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">BVN Enrollment</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">JAMB Pin Purchase</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">CAC Business Reg</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">Digital Consultancy</a></li>
                    </ul>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-bold mb-6">Company</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="#" class="hover:text-primary-400 transition-colors">About Us</a></li>
                        <li><a href="#services" class="hover:text-primary-400 transition-colors">Our Services</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition-colors">Terms of Service</a></li>
                        <li><a href="#contact" class="hover:text-primary-400 transition-colors">Contact Support</a></li>
                    </ul>
                </div>

                <!-- Newsletter -->
                <div>
                    <h4 class="text-white font-bold mb-6">Newsletter</h4>
                    <p class="text-sm mb-6">Subscribe to get latest updates and offers.</p>
                    <form action="#" method="POST" class="flex flex-col gap-3">
                        @csrf
                        <input type="email" placeholder="Email address" required class="bg-slate-900 border-none rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-primary-500">
                        <button class="btn-primary w-full py-3 text-sm">Subscribe</button>
                    </form>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-900 flex flex-col md:flex-row justify-between items-center gap-4 text-xs">
                <p>&copy; {{ date('Y') }} A A TASKAR BEGE Agency. All rights reserved.</p>
                <div class="flex gap-6">
                    <a href="#" class="hover:text-white">Privacy Policy</a>
                    <a href="#" class="hover:text-white">Terms</a>
                    <a href="#" class="hover:text-white">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Loader
        window.addEventListener('load', () => {
            const loader = document.getElementById('loader-wrapper');
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        });

        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('py-2');
            } else {
                navbar.classList.remove('py-2');
            }
        });
    </script>
</body>

</html>
