using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Chat.Infrastructure.Persistence.Migrations
{
    /// <inheritdoc />
    public partial class AddWorkThreadProperties : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<string>(
                name: "SubjectCode",
                table: "conversations",
                type: "TEXT",
                maxLength: 100,
                nullable: true);

            migrationBuilder.AddColumn<long>(
                name: "SubjectId",
                table: "conversations",
                type: "INTEGER",
                nullable: true);

            migrationBuilder.AddColumn<string>(
                name: "SubjectTitle",
                table: "conversations",
                type: "TEXT",
                maxLength: 255,
                nullable: true);

            migrationBuilder.AddColumn<string>(
                name: "SubjectType",
                table: "conversations",
                type: "TEXT",
                maxLength: 50,
                nullable: true);

            migrationBuilder.CreateIndex(
                name: "IX_conversations_SubjectType_SubjectId",
                table: "conversations",
                columns: new[] { "SubjectType", "SubjectId" });
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropIndex(
                name: "IX_conversations_SubjectType_SubjectId",
                table: "conversations");

            migrationBuilder.DropColumn(
                name: "SubjectCode",
                table: "conversations");

            migrationBuilder.DropColumn(
                name: "SubjectId",
                table: "conversations");

            migrationBuilder.DropColumn(
                name: "SubjectTitle",
                table: "conversations");

            migrationBuilder.DropColumn(
                name: "SubjectType",
                table: "conversations");
        }
    }
}
