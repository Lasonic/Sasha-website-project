import { useState, useRef, useEffect } from "react";
import { MessageCircle, X, Send } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";

type Message = {
  id: string;
  role: "user" | "assistant";
  content: string;
};

const QUICK_REPLIES = [
  "What services do you offer?",
  "How do I schedule a consultation?",
  "Tell me about retirement planning",
];

const BOT_RESPONSES: Record<string, string> = {
  "what services do you offer?":
    "We offer comprehensive financial planning including retirement planning, investment management, tax strategies, estate planning, and insurance review. Would you like to learn more about any specific service?",
  "how do i schedule a consultation?":
    "You can schedule a free 30-minute consultation by calling us at (555) 123-4567, emailing info@example.com, or using the contact form on this page. We'd love to hear from you!",
  "tell me about retirement planning":
    "Our retirement planning service helps you build a personalized roadmap to financial independence. We analyze your current savings, project future needs, and create a diversified investment strategy tailored to your goals and risk tolerance.",
};

function getBotReply(userMsg: string): string {
  const lower = userMsg.toLowerCase().trim();
  if (BOT_RESPONSES[lower]) return BOT_RESPONSES[lower];
  if (lower.includes("hello") || lower.includes("hi"))
    return "Hello! Welcome to our financial advisory. How can I help you today?";
  if (lower.includes("invest"))
    return "We'd love to discuss investment strategies with you. Our advisors create personalized portfolios based on your risk tolerance and financial goals. Would you like to schedule a consultation?";
  if (lower.includes("tax"))
    return "Tax-efficient strategies are a cornerstone of our practice. We work to minimize your tax burden through smart planning. Want to learn more?";
  return "Thank you for your question! For detailed assistance, I'd recommend scheduling a free consultation with one of our advisors. You can reach us at (555) 123-4567 or use the contact form below.";
}

export default function ChatBot() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "welcome",
      role: "assistant",
      content:
        "Welcome! I'm here to help with your financial questions. How can I assist you today?",
    },
  ]);
  const [input, setInput] = useState("");
  const [typing, setTyping] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typing]);

  const send = (text: string) => {
    if (!text.trim()) return;
    const userMsg: Message = {
      id: Date.now().toString(),
      role: "user",
      content: text.trim(),
    };
    setMessages((prev) => [...prev, userMsg]);
    setInput("");
    setTyping(true);

    setTimeout(() => {
      const reply: Message = {
        id: (Date.now() + 1).toString(),
        role: "assistant",
        content: getBotReply(text),
      };
      setMessages((prev) => [...prev, reply]);
      setTyping(false);
    }, 800);
  };

  return (
    <>
      {/* Floating button */}
      <button
        onClick={() => setOpen((o) => !o)}
        className={cn(
          "fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105",
          open && "scale-0 pointer-events-none"
        )}
        aria-label="Open chat"
      >
        <MessageCircle className="h-6 w-6" />
      </button>

      {/* Chat window */}
      <div
        className={cn(
          "fixed bottom-6 right-6 z-50 flex w-[370px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border bg-card shadow-2xl transition-all duration-300",
          open
            ? "h-[520px] opacity-100 translate-y-0"
            : "h-0 opacity-0 translate-y-4 pointer-events-none"
        )}
      >
        {/* Header */}
        <div className="flex items-center justify-between bg-primary px-5 py-4">
          <div>
            <p className="font-display text-lg font-semibold text-primary-foreground">
              Financial Advisor
            </p>
            <p className="text-xs text-primary-foreground/70">
              We typically reply instantly
            </p>
          </div>
          <button
            onClick={() => setOpen(false)}
            className="rounded-full p-1 text-primary-foreground/70 hover:text-primary-foreground"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Messages */}
        <ScrollArea className="flex-1 px-4 py-3">
          <div className="flex flex-col gap-3">
            {messages.map((m) => (
              <div
                key={m.id}
                className={cn(
                  "max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed",
                  m.role === "user"
                    ? "ml-auto bg-chat-user text-chat-user-foreground rounded-br-md"
                    : "mr-auto bg-secondary text-secondary-foreground rounded-bl-md"
                )}
              >
                {m.content}
              </div>
            ))}
            {typing && (
              <div className="mr-auto flex gap-1 rounded-2xl bg-secondary px-4 py-3 rounded-bl-md">
                <span className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground [animation-delay:0ms]" />
                <span className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground [animation-delay:150ms]" />
                <span className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground [animation-delay:300ms]" />
              </div>
            )}
            <div ref={bottomRef} />
          </div>
        </ScrollArea>

        {/* Quick replies */}
        {messages.length <= 1 && (
          <div className="flex flex-wrap gap-2 px-4 pb-2">
            {QUICK_REPLIES.map((q) => (
              <button
                key={q}
                onClick={() => send(q)}
                className="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary transition hover:bg-primary/10"
              >
                {q}
              </button>
            ))}
          </div>
        )}

        {/* Input */}
        <form
          onSubmit={(e) => {
            e.preventDefault();
            send(input);
          }}
          className="flex items-center gap-2 border-t px-4 py-3"
        >
          <Input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Type your question…"
            className="flex-1 border-none bg-muted/50 focus-visible:ring-1"
          />
          <Button
            type="submit"
            size="icon"
            disabled={!input.trim()}
            className="h-9 w-9 shrink-0 rounded-full"
          >
            <Send className="h-4 w-4" />
          </Button>
        </form>
      </div>
    </>
  );
}
