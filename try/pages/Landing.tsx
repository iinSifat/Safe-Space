import { Link } from "react-router-dom";
import { 
  Heart, 
  Shield, 
  Users, 
  MessageCircle, 
  BookOpen, 
  Trophy, 
  Phone,
  Star,
  ArrowRight,
  CheckCircle2,
  Sparkles
} from "lucide-react";

const Landing = () => {
  const features = [
    {
      icon: Shield,
      title: "Anonymous Sharing",
      description: "Share your thoughts and feelings without revealing your identity. Your privacy is our priority.",
      gradient: "feature-card-teal"
    },
    {
      icon: Users,
      title: "Peer Support",
      description: "Connect with trained supporters who understand your journey and provide compassionate guidance.",
      gradient: "feature-card-purple"
    },
    {
      icon: MessageCircle,
      title: "Professional Consultations",
      description: "Book sessions with verified mental health professionals through secure video or chat.",
      gradient: "feature-card-sunset"
    },
    {
      icon: BookOpen,
      title: "Mental Health Training",
      description: "Access structured courses on stress management, mindfulness, and emotional wellness.",
      gradient: "feature-card-ocean"
    },
  ];

  const stats = [
    { value: "50K+", label: "Community Members" },
    { value: "24/7", label: "Support Available" },
    { value: "100+", label: "Professional Counselors" },
    { value: "98%", label: "User Satisfaction" },
  ];

  const testimonials = [
    {
      quote: "Safe Space gave me the courage to open up for the first time. The anonymity helped me share things I couldn't tell anyone else.",
      author: "Anonymous User",
      role: "Community Member"
    },
    {
      quote: "The peer supporters here truly understand. They don't judge, they just listen and help you feel less alone.",
      author: "Sarah M.",
      role: "2 Years on Platform"
    },
    {
      quote: "Finally, a platform that takes mental health seriously while keeping it accessible and safe for everyone.",
      author: "Dr. James Wilson",
      role: "Clinical Psychologist"
    },
  ];

  return (
    <div className="min-h-screen bg-background overflow-x-hidden">
      {/* Navigation */}
      <nav className="fixed top-0 left-0 right-0 z-50 bg-background/80 backdrop-blur-xl border-b border-border/50">
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
              <Heart className="w-6 h-6 text-white" />
            </div>
            <span className="text-xl font-bold">Safe Space</span>
          </Link>
          
          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="text-muted-foreground hover:text-foreground transition-colors">Features</a>
            <a href="#about" className="text-muted-foreground hover:text-foreground transition-colors">About</a>
            <a href="#testimonials" className="text-muted-foreground hover:text-foreground transition-colors">Stories</a>
          </div>

          <div className="flex items-center gap-3">
            <Link to="/auth" className="btn-ghost">
              Sign In
            </Link>
            <Link to="/auth" className="btn-primary">
              Get Started
            </Link>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative pt-32 pb-20 md:pt-40 md:pb-32 px-6">
        {/* Background Decorations */}
        <div className="absolute top-40 left-10 w-72 h-72 bg-primary/20 rounded-full blur-3xl" />
        <div className="absolute top-60 right-10 w-96 h-96 bg-secondary/20 rounded-full blur-3xl" />
        <div className="absolute bottom-20 left-1/3 w-64 h-64 bg-accent-warm/10 rounded-full blur-3xl" />

        <div className="relative max-w-7xl mx-auto">
          <div className="max-w-4xl mx-auto text-center">
            <div className="inline-flex items-center gap-2 bg-primary/10 text-primary px-4 py-2 rounded-full text-sm font-medium mb-8">
              <Sparkles className="w-4 h-4" />
              Your mental wellness matters
            </div>

            <h1 className="heading-hero text-balance mb-6">
              A Safe Space for Your{" "}
              <span className="gradient-text">Mental Health</span>{" "}
              Journey
            </h1>

            <p className="text-xl md:text-2xl text-muted-foreground max-w-2xl mx-auto mb-10 leading-relaxed">
              Connect anonymously, share openly, and heal together with a supportive 
              community that understands. You're never alone here.
            </p>

            <div className="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
              <Link to="/auth" className="btn-primary text-lg px-10 py-4">
                Join Safe Space
                <ArrowRight className="w-5 h-5" />
              </Link>
              <a href="#features" className="btn-outline text-lg px-10 py-4">
                Learn More
              </a>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-3xl mx-auto">
              {stats.map((stat, index) => (
                <div key={index} className="text-center">
                  <div className="text-3xl md:text-4xl font-bold gradient-text mb-1">
                    {stat.value}
                  </div>
                  <div className="text-sm text-muted-foreground">{stat.label}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-20 md:py-32 px-6 bg-muted/30">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="heading-lg mb-4">Everything You Need to Heal</h2>
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
              Our comprehensive platform offers multiple pathways to mental wellness, 
              all in one safe environment.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-6">
            {features.map((feature, index) => (
              <div key={index} className={`feature-card ${feature.gradient}`}>
                <div className="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center mb-6">
                  <feature.icon className="w-8 h-8" />
                </div>
                <h3 className="text-2xl font-bold mb-3">{feature.title}</h3>
                <p className="text-white/80 text-lg leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>

          {/* Additional Features List */}
          <div className="mt-16 grid md:grid-cols-3 gap-8">
            {[
              { icon: Trophy, text: "Gamified rewards & achievements" },
              { icon: Phone, text: "24/7 emergency crisis support" },
              { icon: Star, text: "Story sharing community" },
            ].map((item, index) => (
              <div key={index} className="flex items-center gap-4 bg-card p-6 rounded-2xl shadow-sm">
                <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                  <item.icon className="w-6 h-6 text-primary" />
                </div>
                <span className="font-medium">{item.text}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* About Section */}
      <section id="about" className="py-20 md:py-32 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="grid lg:grid-cols-2 gap-16 items-center">
            <div>
              <h2 className="heading-lg mb-6">
                Built by People Who{" "}
                <span className="gradient-text">Understand</span>
              </h2>
              <p className="text-lg text-muted-foreground mb-8 leading-relaxed">
                Safe Space was created by Team Coruscant with a mission to make mental 
                health support accessible, anonymous, and stigma-free. We believe everyone 
                deserves a safe place to share their struggles and find support.
              </p>

              <div className="space-y-4">
                {[
                  "End-to-end encryption for all conversations",
                  "Verified mental health professionals",
                  "Evidence-based therapeutic approaches",
                  "Community-driven peer support",
                  "Free resources and crisis support",
                ].map((item, index) => (
                  <div key={index} className="flex items-center gap-3">
                    <CheckCircle2 className="w-5 h-5 text-primary flex-shrink-0" />
                    <span>{item}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="relative">
              <div className="absolute inset-0 bg-gradient-to-br from-primary/20 to-secondary/20 rounded-3xl blur-2xl" />
              <div className="relative bg-card rounded-3xl p-8 shadow-xl border border-border/50">
                <div className="text-center mb-8">
                  <div className="w-20 h-20 mx-auto rounded-full bg-primary/10 flex items-center justify-center mb-4">
                    <Heart className="w-10 h-10 text-primary" />
                  </div>
                  <h3 className="text-2xl font-bold mb-2">Start Your Journey</h3>
                  <p className="text-muted-foreground">Join our supportive community today</p>
                </div>

                <Link to="/auth" className="btn-primary w-full justify-center text-lg py-4 mb-4">
                  Create Free Account
                </Link>
                <p className="text-center text-sm text-muted-foreground">
                  No credit card required • 100% confidential
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Testimonials */}
      <section id="testimonials" className="py-20 md:py-32 px-6 bg-muted/30">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="heading-lg mb-4">Stories from Our Community</h2>
            <p className="text-xl text-muted-foreground">
              Real experiences from people who found support in Safe Space
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {testimonials.map((testimonial, index) => (
              <div key={index} className="card-elevated">
                <div className="flex gap-1 mb-4">
                  {[...Array(5)].map((_, i) => (
                    <Star key={i} className="w-5 h-5 text-yellow-400 fill-yellow-400" />
                  ))}
                </div>
                <p className="text-lg mb-6 leading-relaxed serif italic">
                  "{testimonial.quote}"
                </p>
                <div>
                  <div className="font-semibold">{testimonial.author}</div>
                  <div className="text-sm text-muted-foreground">{testimonial.role}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 md:py-32 px-6">
        <div className="max-w-4xl mx-auto text-center">
          <div className="relative">
            <div className="absolute inset-0 bg-gradient-to-r from-primary/30 to-secondary/30 rounded-3xl blur-3xl" />
            <div className="relative bg-gradient-to-br from-primary to-secondary rounded-3xl p-12 md:p-16 text-white">
              <h2 className="text-4xl md:text-5xl font-bold mb-6">
                Your Safe Space Awaits
              </h2>
              <p className="text-xl text-white/80 mb-10 max-w-2xl mx-auto">
                Take the first step toward better mental health. Join thousands who have 
                found support, understanding, and hope in our community.
              </p>
              <Link to="/auth" className="inline-flex items-center gap-2 bg-white text-primary font-semibold text-lg px-10 py-4 rounded-full hover:bg-white/90 transition-all shadow-lg hover:shadow-xl hover:scale-105">
                Get Started Free
                <ArrowRight className="w-5 h-5" />
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-12 px-6 border-t border-border">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col md:flex-row items-center justify-between gap-6">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
                <Heart className="w-6 h-6 text-white" />
              </div>
              <span className="text-xl font-bold">Safe Space</span>
            </div>

            <div className="flex items-center gap-8 text-sm text-muted-foreground">
              <a href="#" className="hover:text-foreground transition-colors">Privacy</a>
              <a href="#" className="hover:text-foreground transition-colors">Terms</a>
              <a href="#" className="hover:text-foreground transition-colors">Contact</a>
              <a href="#" className="hover:text-foreground transition-colors">Help</a>
            </div>

            <div className="text-sm text-muted-foreground">
              © 2025 Safe Space by Team Coruscant
            </div>
          </div>

          <div className="mt-8 pt-8 border-t border-border text-center">
            <p className="text-sm text-muted-foreground">
              If you're in crisis, please call the 988 Suicide & Crisis Lifeline (US) or your local emergency services.
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
};

export default Landing;
