using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Chat.Infrastructure.Persistence.Migrations
{
    /// <inheritdoc />
    public partial class InitialMessenger : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "contact_aliases",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    OwnerId = table.Column<long>(type: "INTEGER", nullable: false),
                    TargetId = table.Column<long>(type: "INTEGER", nullable: false),
                    Alias = table.Column<string>(type: "TEXT", maxLength: 60, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_contact_aliases", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "conversations",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    Type = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                    Title = table.Column<string>(type: "TEXT", maxLength: 120, nullable: true),
                    DirectKey = table.Column<string>(type: "TEXT", maxLength: 50, nullable: true),
                    AvatarPath = table.Column<string>(type: "TEXT", maxLength: 300, nullable: true),
                    AvatarContentType = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                    AvatarVersion = table.Column<long>(type: "INTEGER", nullable: false),
                    CreatedBy = table.Column<long>(type: "INTEGER", nullable: false),
                    LastMessageId = table.Column<long>(type: "INTEGER", nullable: true),
                    LastMessageAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    CreatedAt = table.Column<DateTime>(type: "TEXT", nullable: false),
                    UpdatedAt = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_conversations", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "message_hides",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    MessageId = table.Column<long>(type: "INTEGER", nullable: false),
                    UserId = table.Column<long>(type: "INTEGER", nullable: false),
                    CreatedAt = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_message_hides", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "chat_messages",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    ConversationId = table.Column<long>(type: "INTEGER", nullable: false),
                    SenderId = table.Column<long>(type: "INTEGER", nullable: true),
                    SenderName = table.Column<string>(type: "TEXT", maxLength: 200, nullable: false),
                    Type = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                    Content = table.Column<string>(type: "TEXT", maxLength: 16000, nullable: false),
                    ReplyToId = table.Column<long>(type: "INTEGER", nullable: true),
                    Mentions = table.Column<string>(type: "TEXT", maxLength: 2000, nullable: true),
                    MentionAll = table.Column<bool>(type: "INTEGER", nullable: false),
                    ForwardedFromName = table.Column<string>(type: "TEXT", maxLength: 200, nullable: true),
                    EditedAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    PinnedAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    PinnedById = table.Column<long>(type: "INTEGER", nullable: true),
                    RecalledAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    CreatedAt = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_chat_messages", x => x.Id);
                    table.ForeignKey(
                        name: "FK_chat_messages_chat_messages_ReplyToId",
                        column: x => x.ReplyToId,
                        principalTable: "chat_messages",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.SetNull);
                    table.ForeignKey(
                        name: "FK_chat_messages_conversations_ConversationId",
                        column: x => x.ConversationId,
                        principalTable: "conversations",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "conversation_members",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    ConversationId = table.Column<long>(type: "INTEGER", nullable: false),
                    UserId = table.Column<long>(type: "INTEGER", nullable: false),
                    UserName = table.Column<string>(type: "TEXT", maxLength: 200, nullable: false),
                    Role = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                    LastReadMessageId = table.Column<long>(type: "INTEGER", nullable: true),
                    LastReadAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    Muted = table.Column<bool>(type: "INTEGER", nullable: false),
                    PinnedAt = table.Column<DateTime>(type: "TEXT", nullable: true),
                    JoinedAt = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_conversation_members", x => x.Id);
                    table.ForeignKey(
                        name: "FK_conversation_members_conversations_ConversationId",
                        column: x => x.ConversationId,
                        principalTable: "conversations",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "message_attachments",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    MessageId = table.Column<long>(type: "INTEGER", nullable: false),
                    FileName = table.Column<string>(type: "TEXT", maxLength: 255, nullable: false),
                    ContentType = table.Column<string>(type: "TEXT", maxLength: 150, nullable: false),
                    Size = table.Column<long>(type: "INTEGER", nullable: false),
                    StoragePath = table.Column<string>(type: "TEXT", maxLength: 300, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_message_attachments", x => x.Id);
                    table.ForeignKey(
                        name: "FK_message_attachments_chat_messages_MessageId",
                        column: x => x.MessageId,
                        principalTable: "chat_messages",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "message_reactions",
                columns: table => new
                {
                    Id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    MessageId = table.Column<long>(type: "INTEGER", nullable: false),
                    UserId = table.Column<long>(type: "INTEGER", nullable: false),
                    UserName = table.Column<string>(type: "TEXT", maxLength: 200, nullable: false),
                    Emoji = table.Column<string>(type: "TEXT", maxLength: 16, nullable: false),
                    CreatedAt = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_message_reactions", x => x.Id);
                    table.ForeignKey(
                        name: "FK_message_reactions_chat_messages_MessageId",
                        column: x => x.MessageId,
                        principalTable: "chat_messages",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateIndex(
                name: "IX_chat_messages_ConversationId_Id",
                table: "chat_messages",
                columns: new[] { "ConversationId", "Id" });

            migrationBuilder.CreateIndex(
                name: "IX_chat_messages_ConversationId_PinnedAt",
                table: "chat_messages",
                columns: new[] { "ConversationId", "PinnedAt" });

            migrationBuilder.CreateIndex(
                name: "IX_chat_messages_ReplyToId",
                table: "chat_messages",
                column: "ReplyToId");

            migrationBuilder.CreateIndex(
                name: "IX_contact_aliases_OwnerId_TargetId",
                table: "contact_aliases",
                columns: new[] { "OwnerId", "TargetId" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversation_members_ConversationId_UserId",
                table: "conversation_members",
                columns: new[] { "ConversationId", "UserId" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversation_members_UserId",
                table: "conversation_members",
                column: "UserId");

            migrationBuilder.CreateIndex(
                name: "IX_conversations_DirectKey",
                table: "conversations",
                column: "DirectKey",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversations_LastMessageAt",
                table: "conversations",
                column: "LastMessageAt");

            migrationBuilder.CreateIndex(
                name: "IX_message_attachments_MessageId",
                table: "message_attachments",
                column: "MessageId");

            migrationBuilder.CreateIndex(
                name: "IX_message_hides_MessageId_UserId",
                table: "message_hides",
                columns: new[] { "MessageId", "UserId" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_message_hides_UserId",
                table: "message_hides",
                column: "UserId");

            migrationBuilder.CreateIndex(
                name: "IX_message_reactions_MessageId_UserId",
                table: "message_reactions",
                columns: new[] { "MessageId", "UserId" },
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "contact_aliases");

            migrationBuilder.DropTable(
                name: "conversation_members");

            migrationBuilder.DropTable(
                name: "message_attachments");

            migrationBuilder.DropTable(
                name: "message_hides");

            migrationBuilder.DropTable(
                name: "message_reactions");

            migrationBuilder.DropTable(
                name: "chat_messages");

            migrationBuilder.DropTable(
                name: "conversations");
        }
    }
}
