import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { supabase } from "@/integrations/supabase/client";
import { User, Session } from "@supabase/supabase-js";
import { 
  Heart, 
  LogOut, 
  MessageCircle, 
  BookOpen, 
  Users, 
  Trophy,
  Calendar,
  TrendingUp,
  Sparkles,
  Shield
} from "lucide-react";
import { useToast } from "@/hooks/use-toast";

const Dashboard = () => {
  const [user, setUser] = useState<User | null>(null);
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { toast } = useToast();

  useEffect(() => {
    const { data: { subscription } } = supabase.auth.onAuthStateChange((event, session) => {
      setSession(session);
      setUser(session?.user ?? null);
      setLoading(false);
    });

    supabase.auth.getSession().then(({ data: { session } }) => {
      setSession(session);
      setUser(session?.user ?? null);
      setLoading(false);
    });

    return () => subscription.unsubscribe();
  }, []);

  useEffect(() => {
    if (!loading && !user) {
      navigate("/auth");
    }
  }, [user, loading, navigate]);

  const handleLogout = async () => {
    await supabase.auth.signOut();
    toast({
      title: "Signed out",
      description: "Take care of yourself. See you soon! 💚",
    });
    navigate("/");
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="w-16 h-16 rounded-full border-4 border-primary border-t-transparent animate-spin" />
      </div>
    );
  }

  const quickActions = [
    { icon: MessageCircle, label: "Community", description: "Join discussions", color: "bg-primary" },
    { icon: Users, label: "Peer Support", description: "Talk to someone", color: "bg-secondary" },
    { icon: BookOpen, label: "Learn", description: "Courses & articles", color: "bg-[hsl(24_90%_65%)]" },
    { icon: Calendar, label: "Book Session", description: "Meet a counselor", color: "bg-[hsl(199_89%_70%)]" },
  ];

  const achievements = [
    { icon: "🌱", label: "First Steps", earned: true },
    { icon: "💬", label: "Story Sharer", earned: false },
    { icon: "🤝", label: "Helper", earned: false },
    { icon: "📚", label: "Learner", earned: false },
  ];

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="bg-card border-b border-border sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
              <Heart className="w-6 h-6 text-white" />
            </div>
            <span className="text-xl font-bold">Safe Space</span>
          </div>

          <div className="flex items-center gap-4">
            <div className="hidden sm:flex items-center gap-2 bg-primary/10 text-primary px-4 py-2 rounded-full text-sm font-medium">
              <Trophy className="w-4 h-4" />
              <span>150 Points</span>
            </div>
            <button
              onClick={handleLogout}
              className="btn-ghost text-muted-foreground hover:text-foreground"
            >
              <LogOut className="w-5 h-5" />
              <span className="hidden sm:inline">Sign Out</span>
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-8">
        {/* Welcome Section */}
        <div className="mb-10">
          <div className="flex items-center gap-2 text-primary mb-2">
            <Sparkles className="w-5 h-5" />
            <span className="text-sm font-medium">Welcome back!</span>
          </div>
          <h1 className="text-3xl md:text-4xl font-bold mb-2">
            Hello, {user?.email?.split("@")[0] || "Friend"} 👋
          </h1>
          <p className="text-muted-foreground text-lg">
            How are you feeling today? Remember, you're not alone.
          </p>
        </div>

        {/* Mood Check-in Card */}
        <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary to-secondary p-8 mb-10 text-white">
          <div className="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
          <div className="relative">
            <h2 className="text-2xl font-bold mb-4">Daily Check-in</h2>
            <p className="text-white/80 mb-6 max-w-lg">
              Take a moment to reflect on your emotions. Regular check-ins help you understand your patterns.
            </p>
            <div className="flex flex-wrap gap-3">
              {["😊 Great", "🙂 Good", "😐 Okay", "😔 Low", "😢 Struggling"].map((mood, i) => (
                <button 
                  key={i}
                  className="bg-white/20 hover:bg-white/30 px-5 py-2 rounded-full transition-all hover:scale-105"
                >
                  {mood}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mb-10">
          <h2 className="text-xl font-semibold mb-4">Quick Actions</h2>
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {quickActions.map((action, index) => (
              <button
                key={index}
                className="card-elevated text-left hover:border-primary/50 group"
              >
                <div className={`w-12 h-12 ${action.color} rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                  <action.icon className="w-6 h-6 text-white" />
                </div>
                <h3 className="font-semibold mb-1">{action.label}</h3>
                <p className="text-sm text-muted-foreground">{action.description}</p>
              </button>
            ))}
          </div>
        </div>

        <div className="grid lg:grid-cols-3 gap-8">
          {/* Progress Card */}
          <div className="lg:col-span-2 card-elevated">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold">Your Progress</h2>
              <TrendingUp className="w-5 h-5 text-primary" />
            </div>

            <div className="grid sm:grid-cols-3 gap-6 mb-6">
              <div className="text-center p-4 bg-muted/50 rounded-xl">
                <div className="text-3xl font-bold gradient-text">7</div>
                <div className="text-sm text-muted-foreground">Day Streak</div>
              </div>
              <div className="text-center p-4 bg-muted/50 rounded-xl">
                <div className="text-3xl font-bold gradient-text">3</div>
                <div className="text-sm text-muted-foreground">Courses Started</div>
              </div>
              <div className="text-center p-4 bg-muted/50 rounded-xl">
                <div className="text-3xl font-bold gradient-text">12</div>
                <div className="text-sm text-muted-foreground">People Helped</div>
              </div>
            </div>

            <div>
              <div className="flex justify-between text-sm mb-2">
                <span className="text-muted-foreground">Weekly Goal</span>
                <span className="font-medium">60%</span>
              </div>
              <div className="h-3 bg-muted rounded-full overflow-hidden">
                <div className="h-full w-[60%] bg-gradient-to-r from-primary to-secondary rounded-full" />
              </div>
            </div>
          </div>

          {/* Achievements Card */}
          <div className="card-elevated">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold">Achievements</h2>
              <Trophy className="w-5 h-5 text-yellow-500" />
            </div>

            <div className="space-y-4">
              {achievements.map((achievement, index) => (
                <div 
                  key={index}
                  className={`flex items-center gap-4 p-3 rounded-xl ${
                    achievement.earned ? "bg-primary/10" : "bg-muted/50 opacity-60"
                  }`}
                >
                  <div className="text-2xl">{achievement.icon}</div>
                  <div>
                    <div className="font-medium">{achievement.label}</div>
                    <div className="text-xs text-muted-foreground">
                      {achievement.earned ? "Earned!" : "Keep going..."}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Resources Banner */}
        <div className="mt-10 bg-card rounded-2xl p-6 border border-border flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
              <Shield className="w-6 h-6 text-red-500" />
            </div>
            <div>
              <h3 className="font-semibold">Need immediate help?</h3>
              <p className="text-sm text-muted-foreground">Crisis support is available 24/7</p>
            </div>
          </div>
          <button className="bg-red-500 hover:bg-red-600 text-white font-semibold px-6 py-3 rounded-full transition-colors">
            Emergency Resources
          </button>
        </div>
      </main>
    </div>
  );
};

export default Dashboard;
