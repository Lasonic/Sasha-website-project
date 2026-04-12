<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sasha Coaching - Meridian Financial Advisors</title>
    <?php wp_head(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          container: {
            center: true,
            padding: "2rem",
            screens: {
              "2xl": "1400px",
            },
          },
          extend: {
            colors: {
              border: "hsl(var(--border))",
              input: "hsl(var(--input))",
              ring: "hsl(var(--ring))",
              background: "hsl(var(--background))",
              foreground: "hsl(var(--foreground))",
              primary: {
                DEFAULT: "hsl(var(--primary))",
                foreground: "hsl(var(--primary-foreground))",
              },
              secondary: {
                DEFAULT: "hsl(var(--secondary))",
                foreground: "hsl(var(--secondary-foreground))",
              },
              destructive: {
                DEFAULT: "hsl(var(--destructive))",
                foreground: "hsl(var(--destructive-foreground))",
              },
              muted: {
                DEFAULT: "hsl(var(--muted))",
                foreground: "hsl(var(--muted-foreground))",
              },
              accent: {
                DEFAULT: "hsl(var(--accent))",
                foreground: "hsl(var(--accent-foreground))",
              },
              popover: {
                DEFAULT: "hsl(var(--popover))",
                foreground: "hsl(var(--popover-foreground))",
              },
              card: {
                DEFAULT: "hsl(var(--card))",
                foreground: "hsl(var(--card-foreground))",
              },
            },
            borderRadius: {
              lg: "var(--radius)",
              md: "calc(var(--radius) - 2px)",
              sm: "calc(var(--radius) - 4px)",
            },
          },
        },
      }
    </script>
    <style>
      .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: 500; transition: all 0.2s; white-space: nowrap; }
      .btn-lg { height: 3.5rem; padding: 0 2rem; font-size: 1rem; border-radius: 9999px; }
      .btn-sm { height: 2.25rem; padding: 0 1.25rem; font-size: 0.875rem; border-radius: 9999px; }
      .btn-primary { background-color: hsl(var(--primary)); color: hsl(var(--primary-foreground)); }
      .btn-primary:hover { opacity: 0.9; }
      .btn-outline { border: 1px solid hsl(var(--input)); background-color: transparent; }
      .btn-outline:hover { background-color: hsl(var(--accent)); color: hsl(var(--accent-foreground)); }
      .input { display: flex; height: 2.5rem; width: 100%; border-radius: calc(var(--radius) - 2px); border: 1px solid hsl(var(--input)); background-color: transparent; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
      .textarea { display: flex; min-height: 120px; width: 100%; border-radius: calc(var(--radius) - 2px); border: 1px solid hsl(var(--input)); background-color: transparent; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
      .input:focus-visible, .textarea:focus-visible { outline: 2px solid hsl(var(--ring)); outline-offset: 2px; }
    </style>
</head>
<body <?php body_class( 'antialiased' ); ?>>
<?php wp_body_open(); ?>

<div class="min-h-screen bg-background font-sans">
  <!-- Nav -->
  <header class="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur-md">
    <div class="container flex h-16 items-center justify-between">
      <a href="#" class="font-display text-xl font-bold text-primary">
        Meridian<span class="text-accent">.</span>
      </a>
      <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
        <a href="#services" class="text-muted-foreground transition hover:text-foreground">Services</a>
        <a href="#about" class="text-muted-foreground transition hover:text-foreground">About</a>
        <a href="#contact" class="text-muted-foreground transition hover:text-foreground">Contact</a>
      </nav>
      <button class="btn btn-sm btn-primary">
        Book a Call 
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
  </header>

  <!-- Hero -->
  <section class="relative overflow-hidden py-24 md:py-36">
    <div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-accent/5"></div>
    <div class="container max-w-3xl text-center">
      <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-accent">Trusted Financial Guidance</p>
      <h1 class="font-display text-4xl font-bold leading-tight text-foreground md:text-6xl">
        Your Wealth, <br class="hidden md:block" />
        Thoughtfully Managed
      </h1>
      <p class="mx-auto mt-6 max-w-xl text-lg text-muted-foreground">
        We combine decades of market expertise with a personal approach—so you can focus on what matters while your finances work harder.
      </p>
      <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
        <button class="btn btn-lg btn-primary">Schedule Free Consultation</button>
        <button class="btn btn-lg btn-outline">Learn More</button>
      </div>
    </div>
  </section>

  <!-- Stats -->
  <section class="border-y border-border bg-primary py-12">
    <div class="container grid grid-cols-2 gap-8 md:grid-cols-4">
      <div class="text-center">
        <p class="font-display text-3xl font-bold text-primary-foreground md:text-4xl">20+</p>
        <p class="mt-1 text-sm text-primary-foreground/70">Years Experience</p>
      </div>
      <div class="text-center">
        <p class="font-display text-3xl font-bold text-primary-foreground md:text-4xl">$350M</p>
        <p class="mt-1 text-sm text-primary-foreground/70">Assets Managed</p>
      </div>
      <div class="text-center">
        <p class="font-display text-3xl font-bold text-primary-foreground md:text-4xl">1,200+</p>
        <p class="mt-1 text-sm text-primary-foreground/70">Clients Served</p>
      </div>
      <div class="text-center">
        <p class="font-display text-3xl font-bold text-primary-foreground md:text-4xl">98%</p>
        <p class="mt-1 text-sm text-primary-foreground/70">Retention Rate</p>
      </div>
    </div>
  </section>

  <!-- Services -->
  <section id="services" class="py-20 md:py-28">
    <div class="container">
      <p class="text-center text-sm font-semibold uppercase tracking-widest text-accent">What We Do</p>
      <h2 class="mt-3 text-center font-display text-3xl font-bold text-foreground md:text-4xl">
        Comprehensive Financial Services
      </h2>
      <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        
        <div class="group rounded-xl border border-transparent bg-secondary/50 p-6 transition hover:-translate-y-1 hover:shadow-lg">
          <div class="flex h-12 w-12 flex-col items-center justify-center rounded-xl bg-accent/15 text-accent transition group-hover:bg-accent group-hover:text-accent-foreground mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
          </div>
          <h3 class="font-display text-lg font-semibold mb-2">Investment Management</h3>
          <p class="text-sm leading-relaxed text-muted-foreground">Personalized portfolios built around your goals, risk tolerance, and timeline.</p>
        </div>

        <div class="group rounded-xl border border-transparent bg-secondary/50 p-6 transition hover:-translate-y-1 hover:shadow-lg">
          <div class="flex h-12 w-12 flex-col items-center justify-center rounded-xl bg-accent/15 text-accent transition group-hover:bg-accent group-hover:text-accent-foreground mb-4">
             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.5-1 2-1.5L21 13c1.1-1.7.4-4-1.8-4.4L19 8.5V5Z"/><path d="M2 9v1c0 1.1.9 2 2 2h1"/><path d="M16 11h.01"/></svg>
          </div>
          <h3 class="font-display text-lg font-semibold mb-2">Retirement Planning</h3>
          <p class="text-sm leading-relaxed text-muted-foreground">A clear roadmap to financial independence—whether retirement is 5 or 25 years away.</p>
        </div>

        <div class="group rounded-xl border border-transparent bg-secondary/50 p-6 transition hover:-translate-y-1 hover:shadow-lg">
          <div class="flex h-12 w-12 flex-col items-center justify-center rounded-xl bg-accent/15 text-accent transition group-hover:bg-accent group-hover:text-accent-foreground mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2-1 4-3 5.92-3a1 1 0 0 1 .16 0C13 2.5 15 4.5 17 5.5a1 1 0 0 1 1 1V13Z"/></svg>
          </div>
          <h3 class="font-display text-lg font-semibold mb-2">Tax & Estate Strategy</h3>
          <p class="text-sm leading-relaxed text-muted-foreground">Protect what you've built with proactive tax planning and estate structuring.</p>
        </div>

        <div class="group rounded-xl border border-transparent bg-secondary/50 p-6 transition hover:-translate-y-1 hover:shadow-lg">
          <div class="flex h-12 w-12 flex-col items-center justify-center rounded-xl bg-accent/15 text-accent transition group-hover:bg-accent group-hover:text-accent-foreground mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3 class="font-display text-lg font-semibold mb-2">Family Wealth</h3>
          <p class="text-sm leading-relaxed text-muted-foreground">Multi-generational planning so your legacy endures for those who matter most.</p>
        </div>

      </div>
    </div>
  </section>

  <!-- About -->
  <section id="about" class="bg-secondary/40 py-20 md:py-28">
    <div class="container grid items-center gap-12 md:grid-cols-2">
      <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-primary/10">
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary/30"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2-1 4-3 5.92-3a1 1 0 0 1 .16 0C13 2.5 15 4.5 17 5.5a1 1 0 0 1 1 1V13Z"/></svg>
            <p class="mt-4 text-sm text-muted-foreground">Your trusted partner</p>
        </div>
      </div>
      <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-accent">About Us</p>
        <h2 class="mt-3 font-display text-3xl font-bold text-foreground md:text-4xl">
          Built on Trust, Driven by Results
        </h2>
        <p class="mt-6 leading-relaxed text-muted-foreground">
          For over two decades, Meridian Financial has helped individuals, families, and business owners navigate the complexities of wealth management. Our fiduciary-first approach means your interests always come first.
        </p>
        <p class="mt-4 leading-relaxed text-muted-foreground">
          Every client receives a dedicated advisor, a customized plan, and ongoing reviews to keep your strategy aligned with life's changes.
        </p>
        <button class="btn btn-sm btn-primary mt-8">
          Meet the Team <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
    </div>
  </section>

  <!-- Contact -->
  <section id="contact" class="py-20 md:py-28">
    <div class="container grid gap-12 md:grid-cols-2">
      <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-accent">Get In Touch</p>
        <h2 class="mt-3 font-display text-3xl font-bold text-foreground md:text-4xl">
          Start a Conversation
        </h2>
        <p class="mt-4 leading-relaxed text-muted-foreground">
          Whether you're just beginning your financial journey or looking for a new advisor, we're here to help.
        </p>
        <div class="mt-8 flex flex-col gap-5">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </div>
            <span class="text-sm">(555) 123-4567</span>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            </div>
            <span class="text-sm">info@meridianfinancial.com</span>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 15 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <span class="text-sm">123 Park Avenue, Suite 800, New York, NY</span>
          </div>
        </div>
      </div>
      <div class="rounded-xl border border-border bg-card p-6 shadow-lg">
        <div class="flex flex-col gap-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <input type="text" class="input" placeholder="First name" />
            <input type="text" class="input" placeholder="Last name" />
          </div>
          <input type="email" class="input" placeholder="Email address" />
          <input type="tel" class="input" placeholder="Phone number" />
          <textarea class="textarea" placeholder="How can we help you?"></textarea>
          <button class="btn btn-primary" style="width: 100%; border-radius: 9999px; height: 2.5rem;">Send Message</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="border-t border-border bg-primary py-10 text-primary-foreground/70">
    <div class="container flex flex-col items-center justify-between gap-4 md:flex-row">
      <a href="#" class="font-display text-lg font-bold text-primary-foreground">
        Meridian<span class="text-accent">.</span>
      </a>
      <p class="text-xs">© <?php echo date('Y'); ?> Meridian Financial Advisors. All rights reserved.</p>
    </div>
  </footer>

  <!-- Chatbot overlay injected by WordPress Page Content -->
  <?php if (have_posts()) : while (have_posts()) : the_post(); the_content(); endwhile; endif; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
