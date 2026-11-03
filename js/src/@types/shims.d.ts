import 'flarum/common/models/User';
import 'flarum/forum/ForumApplication';

declare module 'flarum/common/models/User' {
  export default interface User {
    canCheckin(): boolean | undefined;
    totalContinuousCheckIn(): number | undefined;
    totalCheckinCount(): number | undefined;
    lastCheckinTime(): string | null | undefined;
    checkinCard(): number | undefined;
  }
}

declare module 'flarum/forum/ForumApplication' {
  export default interface ForumApplication {
    checkinToday?: string;
    allowCheckIn?: boolean;
    canQueryOthersHistory?: boolean;
    forumAutoCheckin?: number;
    forumAutoCheckinDelay?: number;
    forumCheckinTimeZone?: number;
    forumCheckinSuccessPromptType?: number;
    forumCheckinSuccessPromptText?: string;
    forumCheckinSuccessPromptRewardText?: string;
    forumCheckinRewardMoney?: number;
    checkinCdnResources?: {
      fullcalendar?: {
        url?: string;
        sri?: string;
      };
      locales?: {
        url?: string;
        sri?: string;
      };
    };
  }
}

declare global {
  const FullCalendar: any;
}
