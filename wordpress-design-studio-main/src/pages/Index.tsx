import { Shield, TrendingUp, PiggyBank, Users, ChevronRight, Phone, Mail, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import ChatBot from "@/components/ChatBot";

const services = [
  {
    icon: TrendingUp,
    title: "Investment Management",
    desc: "Personalized portfolios built around your goals, risk tolerance, and timeline.",
  },
  {
    icon: PiggyBank,
    title: "Retirement Planning",
    desc: "A clear roadmap to financial independence—whether retirement is 5 or 25 years away.",
  },
  {
    icon: Shield,
    title: "Tax & Estate Strategy",
    desc: "Protect what you've built with proactive tax planning and estate structuring.",
  },
  {
    icon: Users,
    title: "Family Wealth",
    desc: "Multi-generational planning so your legacy endures for those who matter most.",
  },
];

const stats = [
  { value: "20+", label: "Years Experience" },
  { value: "$350M", label: "Assets Managed" },
  { value: "1,200+", label: "Clients Served" },
  { value: "98%", label: "Retention Rate" },
];

export default function Index() {
  return (
    <div className="min-h-screen bg-background font-body">
      {/* Nav */}
      <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur-md">
        <div className="container flex h-16 items-center justify-between">
          <a href="#" className="font-display text-xl font-bold text-primary">
            Meridian<span className="text-accent">.</span>
          </a>
          <nav className="hidden items-center gap-8 text-sm font-medium md:flex">
            <a href="#services" className="text-muted-foreground transition hover:text-foreground">Services</a>
            <a href="#about" className="text-muted-foreground transition hover:text-foreground">About</a>
            <a href="#contact" className="text-muted-foreground transition hover:text-foreground">Contact</a>
          </nav>
          <Button size="sm" className="rounded-full px-5">
            Book a Call <ChevronRight className="ml-1 h-4 w-4" />
          </Button>
        </div>
      </header>

      {/* Hero */}
      <section className="relative overflow-hidden py-24 md:py-36">
        <div className="absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-accent/5" />
        <div className="container max-w-3xl text-center">
          <p className="mb-4 text-sm font-semibold uppercase tracking-widest text-accent">Trusted Financial Guidance</p>
          <h1 className="font-display text-4xl font-bold leading-tight text-foreground md:text-6xl">
            Your Wealth, <br className="hidden md:block" />
            Thoughtfully Managed
          </h1>
          <p className="mx-auto mt-6 max-w-xl text-lg text-muted-foreground">
            We combine decades of market expertise with a personal approach—so you can focus on what matters while your finances work harder.
          </p>
          <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            <Button size="lg" className="rounded-full px-8 text-base">
              Schedule Free Consultation
            </Button>
            <Button size="lg" variant="outline" className="rounded-full px-8 text-base">
              Learn More
            </Button>
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="border-y bg-primary py-12">
        <div className="container grid grid-cols-2 gap-8 md:grid-cols-4">
          {stats.map((s) => (
            <div key={s.label} className="text-center">
              <p className="font-display text-3xl font-bold text-primary-foreground md:text-4xl">{s.value}</p>
              <p className="mt-1 text-sm text-primary-foreground/70">{s.label}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Services */}
      <section id="services" className="py-20 md:py-28">
        <div className="container">
          <p className="text-center text-sm font-semibold uppercase tracking-widest text-accent">What We Do</p>
          <h2 className="mt-3 text-center font-display text-3xl font-bold text-foreground md:text-4xl">
            Comprehensive Financial Services
          </h2>
          <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {services.map((s) => (
              <Card
                key={s.title}
                className="group border-none bg-secondary/50 transition hover:shadow-lg hover:-translate-y-1"
              >
                <CardContent className="flex flex-col items-start gap-4 p-6">
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-accent/15 text-accent transition group-hover:bg-accent group-hover:text-accent-foreground">
                    <s.icon className="h-6 w-6" />
                  </div>
                  <h3 className="font-display text-lg font-semibold">{s.title}</h3>
                  <p className="text-sm leading-relaxed text-muted-foreground">{s.desc}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* About */}
      <section id="about" className="bg-secondary/40 py-20 md:py-28">
        <div className="container grid items-center gap-12 md:grid-cols-2">
          <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-primary/10">
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="text-center">
                <Shield className="mx-auto h-16 w-16 text-primary/30" />
                <p className="mt-4 text-sm text-muted-foreground">Your trusted partner</p>
              </div>
            </div>
          </div>
          <div>
            <p className="text-sm font-semibold uppercase tracking-widest text-accent">About Us</p>
            <h2 className="mt-3 font-display text-3xl font-bold text-foreground md:text-4xl">
              Built on Trust, Driven by Results
            </h2>
            <p className="mt-6 leading-relaxed text-muted-foreground">
              For over two decades, Meridian Financial has helped individuals, families, and business owners navigate the complexities of wealth management. Our fiduciary-first approach means your interests always come first.
            </p>
            <p className="mt-4 leading-relaxed text-muted-foreground">
              Every client receives a dedicated advisor, a customized plan, and ongoing reviews to keep your strategy aligned with life's changes.
            </p>
            <Button className="mt-8 rounded-full px-6">
              Meet the Team <ChevronRight className="ml-1 h-4 w-4" />
            </Button>
          </div>
        </div>
      </section>

      {/* Contact */}
      <section id="contact" className="py-20 md:py-28">
        <div className="container grid gap-12 md:grid-cols-2">
          <div>
            <p className="text-sm font-semibold uppercase tracking-widest text-accent">Get In Touch</p>
            <h2 className="mt-3 font-display text-3xl font-bold text-foreground md:text-4xl">
              Start a Conversation
            </h2>
            <p className="mt-4 leading-relaxed text-muted-foreground">
              Whether you're just beginning your financial journey or looking for a new advisor, we're here to help.
            </p>
            <div className="mt-8 flex flex-col gap-5">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
                  <Phone className="h-4 w-4" />
                </div>
                <span className="text-sm">(555) 123-4567</span>
              </div>
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
                  <Mail className="h-4 w-4" />
                </div>
                <span className="text-sm">info@meridianfinancial.com</span>
              </div>
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
                  <MapPin className="h-4 w-4" />
                </div>
                <span className="text-sm">123 Park Avenue, Suite 800, New York, NY</span>
              </div>
            </div>
          </div>
          <Card className="border-none shadow-lg">
            <CardContent className="flex flex-col gap-4 p-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <Input placeholder="First name" />
                <Input placeholder="Last name" />
              </div>
              <Input placeholder="Email address" type="email" />
              <Input placeholder="Phone number" type="tel" />
              <Textarea placeholder="How can we help you?" className="min-h-[120px]" />
              <Button className="w-full rounded-full">Send Message</Button>
            </CardContent>
          </Card>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t bg-primary py-10 text-primary-foreground/70">
        <div className="container flex flex-col items-center justify-between gap-4 md:flex-row">
          <a href="#" className="font-display text-lg font-bold text-primary-foreground">
            Meridian<span className="text-accent">.</span>
          </a>
          <p className="text-xs">© {new Date().getFullYear()} Meridian Financial Advisors. All rights reserved.</p>
        </div>
      </footer>

      {/* Chatbot overlay */}
      <ChatBot />
    </div>
  );
}
