import controller.StudentController;
import model.Student;

public class Main {
    public static void main(String[] args) {
        StudentController controller = new StudentController();

        controller.afficherEtudiants();

        System.out.println("\nAjout d'un nouvel étudiant...\n");
        Student nouvelEtudiant = new Student(3, "Nora", "nora@mail.com");
        controller.ajouterEtudiant(nouvelEtudiant);

        controller.afficherEtudiants();
    }
}
