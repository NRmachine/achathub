package controller;

import java.util.ArrayList;
import java.util.List;
import model.Student;

public class StudentController {
    private final List<Student> students;

    public StudentController() {
        students = new ArrayList<>();
        students.add(new Student(1, "Ali", "ali@mail.com"));
        students.add(new Student(2, "Camille", "camille@mail.com"));
    }

    public void afficherEtudiants() {
        System.out.println("Liste des étudiants :");
        for (Student student : students) {
            System.out.println(
                student.getId() + " - "
                + student.getName() + " - "
                + student.getEmail()
            );
        }
    }

    public void ajouterEtudiant(Student student) {
        students.add(student);
    }
}
