package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"io"
	"jconnect/go-backend/internal/service"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

func (a *App) photo(c *fiber.Ctx, field string) (string, error) {
	if !strings.HasPrefix(c.Get("Content-Type"), "multipart/form-data") {
		return "", nil
	}
	file, err := c.FormFile(field)
	if err != nil {
		return "", nil
	}
	if !a.Write {
		return "", fiber.NewError(423, "Read-only verification; photo was not stored")
	}
	if file.Size > 2*1024*1024 {
		return "", invalid("Photo exceeds 2 MB")
	}
	reader, err := file.Open()
	if err != nil {
		return "", err
	}
	defer reader.Close()
	b, err := io.ReadAll(io.LimitReader(reader, 2*1024*1024+1))
	if err != nil {
		return "", err
	}
	ext := map[string]string{"image/png": ".png", "image/jpeg": ".jpg", "image/gif": ".gif", "image/webp": ".webp"}[http.DetectContentType(b)]
	if ext == "" {
		return "", invalid("Choose a PNG, JPEG, GIF or WebP photo")
	}
	name := service.Token() + ext
	if err = os.WriteFile(filepath.Join(a.Root, "frontend/assets/images", name), b, 0644); err != nil {
		return "", err
	}
	return name, nil
}
