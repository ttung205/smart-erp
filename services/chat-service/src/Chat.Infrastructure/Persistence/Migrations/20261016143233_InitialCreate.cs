using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Chat.Infrastructure.Persistence.Migrations
{
    /// <inheritdoc />
    public partial class InitialCreate : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "conversations",
                columns: table => new
                {
                    id = table.Column<Guid>(type: "TEXT", nullable: false),
                    type = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    code = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    name = table.Column<string>(type: "TEXT", maxLength: 150, nullable: false),
                    department_code = table.Column<string>(type: "TEXT", maxLength: 50, nullable: true),
                    description = table.Column<string>(type: "TEXT", maxLength: 500, nullable: true),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_conversations", x => x.id);
                });

            migrationBuilder.CreateTable(
                name: "chat_messages",
                columns: table => new
                {
                    id = table.Column<Guid>(type: "TEXT", nullable: false),
                    conversation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                    sender_id = table.Column<int>(type: "INTEGER", nullable: false),
                    sender_name = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    sender_avatar = table.Column<string>(type: "TEXT", maxLength: 255, nullable: true),
                    message_type = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    content = table.Column<string>(type: "TEXT", nullable: false),
                    attachment_url = table.Column<string>(type: "TEXT", maxLength: 500, nullable: true),
                    metadata_json = table.Column<string>(type: "TEXT", nullable: true),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_chat_messages", x => x.id);
                    table.ForeignKey(
                        name: "FK_chat_messages_conversations_conversation_id",
                        column: x => x.conversation_id,
                        principalTable: "conversations",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "conversation_members",
                columns: table => new
                {
                    id = table.Column<Guid>(type: "TEXT", nullable: false),
                    conversation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                    user_id = table.Column<int>(type: "INTEGER", nullable: false),
                    user_name = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                    user_email = table.Column<string>(type: "TEXT", maxLength: 150, nullable: true),
                    user_avatar = table.Column<string>(type: "TEXT", maxLength: 255, nullable: true),
                    role = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    joined_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_conversation_members", x => x.id);
                    table.ForeignKey(
                        name: "FK_conversation_members_conversations_conversation_id",
                        column: x => x.conversation_id,
                        principalTable: "conversations",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "message_receipts",
                columns: table => new
                {
                    id = table.Column<Guid>(type: "TEXT", nullable: false),
                    message_id = table.Column<Guid>(type: "TEXT", nullable: false),
                    user_id = table.Column<int>(type: "INTEGER", nullable: false),
                    read_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_message_receipts", x => x.id);
                    table.ForeignKey(
                        name: "FK_message_receipts_chat_messages_message_id",
                        column: x => x.message_id,
                        principalTable: "chat_messages",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateIndex(
                name: "IX_chat_messages_conversation_id_created_at",
                table: "chat_messages",
                columns: new[] { "conversation_id", "created_at" });

            migrationBuilder.CreateIndex(
                name: "IX_chat_messages_sender_id",
                table: "chat_messages",
                column: "sender_id");

            migrationBuilder.CreateIndex(
                name: "IX_conversation_members_conversation_id_user_id",
                table: "conversation_members",
                columns: new[] { "conversation_id", "user_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversation_members_user_id",
                table: "conversation_members",
                column: "user_id");

            migrationBuilder.CreateIndex(
                name: "IX_conversations_code",
                table: "conversations",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversations_created_at",
                table: "conversations",
                column: "created_at");

            migrationBuilder.CreateIndex(
                name: "IX_conversations_department_code",
                table: "conversations",
                column: "department_code");

            migrationBuilder.CreateIndex(
                name: "IX_message_receipts_message_id_user_id",
                table: "message_receipts",
                columns: new[] { "message_id", "user_id" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_message_receipts_user_id",
                table: "message_receipts",
                column: "user_id");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "conversation_members");

            migrationBuilder.DropTable(
                name: "message_receipts");

            migrationBuilder.DropTable(
                name: "chat_messages");

            migrationBuilder.DropTable(
                name: "conversations");
        }
    }
}
